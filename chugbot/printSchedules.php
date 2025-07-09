<?php
    session_start();
    include_once 'dbConn.php';
    include_once 'functions.php';
    bounceToLogin("rosh");
    checkLogout();
    setup_camp_specific_terminology_constants();
    echo headerText("Print Schedules");

    // Get info from the form
    $edahId = test_post_input('edah');
    $blockId = test_post_input('block');
    $scheduleTemplate = html_entity_decode(test_post_input('schedule-template'));

    $camperId2Name = array();

    $blockIdsOverride = [];
    // Check to see if a "schedule" variable is passed as part of the POST command; if so, ignore it
    // This happens when there is a template - the dropdown to select the template only shows when templates exist, 
    //     and then the element becomes part of the form. This adjustment accounts for the potential discrepancy
    $postNotInclude = 3;
    if(array_key_exists("schedule", $_POST)) {
	    $postNotInclude++;
    }
    // Loop through all blocks, which is the number of values in the POST request minus 4 (excludes edah, block, schedule, and starts at 0)
    for ($i = 0; $i < count($_POST) - $postNotInclude; $i ++) {
        $temp = $_POST[$i];
        if (is_numeric($temp)) {
            array_push($blockIdsOverride, $temp);
        }
        else {
            array_push($blockIdsOverride, $blockId);
        }
    }


    // ***************************************************************************
    // **************************** Build SQL Queries ****************************
    // ***************************************************************************

    // Begin by creating a SQL query to get names of all chug groups which the
    // selected edah has available
    $localErr = "";
    $dbc = new DbConn();
    $sql = "SELECT e.group_id group_id, g.name group_name FROM edot_for_group e, chug_groups g WHERE e.edah_id IN (" . 
        $edahId . ") AND e.group_id = g.group_id GROUP BY e.group_id HAVING COUNT(e.edah_id) = 1";

    $result = $dbc->doQuery($sql, $localErr);
    if ($result == false) {
        echo dbErrorString($sql, $localErr);
        exit();
    }

    // Next, build sub-query for the included groups -- notably, also saves the groups
    // (from previous query) to $chugGroups
    $groupClause = "";
    $i = 0;
    $chugGroups = []; // Array which will hold every applicable chug group
    while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
        $gn = $row[1];
        array_push($chugGroups, $gn);
        $groupClause .= " MAX(CASE WHEN g.group_name = \"" . $gn . "\" THEN g.chug_name ELSE NULL END) AS \"" . $gn . "\"";
        if ($i++ < ($result->num_rows - 1)) {
            $groupClause .= ", ";
        }
    }

    // Now moving on to the complete query, broken down into a couple parts:

    // sub-part 1: sql query returning table with info about each camper in one edah for a specific block
    $camperSqlForBlock = "SELECT CONCAT(c.first, ' ', c.last) AS name, c.camper_id AS camper_id, bu.name AS bunk, " .
        "c.bunk_id, e.name AS edah, e.edah_id, e.rosh_name AS rosh, e.rosh_phone AS roshphone, p.block_id AS block_id, b.name AS BLOCK, c.last " .
        "FROM campers c JOIN edot e ON c.edah_id = e.edah_id LEFT JOIN bunks bu ON c.bunk_id = bu.bunk_id " .
            "JOIN (SELECT m.camper_id camper_id, c.name chug_name, c.chug_id chug_id, g.name group_name, b.name block_name, " .
                "b.block_id block_id " .
                    "FROM chug_groups g, blocks b, (matches m, chug_instances i, chugim c) " .
                    "WHERE i.block_id = b.block_id AND c.chug_id = i.chug_id AND c.group_id = g.group_id " .
                    " AND m.chug_instance_id = i.chug_instance_id ) p " .
        "JOIN blocks AS b ON b.block_id = p.block_id WHERE c.camper_id = p.camper_id AND c.edah_id = e.edah_id " .
        "AND e.edah_id = " . $edahId . " AND b.block_id = " . $blockId .
        " GROUP BY camper_id, block_id ORDER BY name";

    // sub-part 2: sql query returning table with each camper's chug assignments for every block and group name
    $assignmentSqlForBlocks = "SELECT m.camper_id camper_id, c.name chug_name, c.chug_id chug_id, g.name group_name, " .
        "b.name block_name, b.block_id block_id " .
        "FROM chug_groups g, blocks b, (matches m, chug_instances i, chugim c) " .
        "WHERE i.block_id = b.block_id AND c.chug_id = i.chug_id AND c.group_id = g.group_id " .
        "AND m.chug_instance_id = i.chug_instance_id";
    
    // Full sql query
    $sql = "SELECT p.camper_id, p.name, p.bunk, p.edah, p.rosh, p.roshphone, " .
        $groupClause .
        " FROM (" . $camperSqlForBlock . ") p " . 
        "LEFT OUTER JOIN (" . $assignmentSqlForBlocks . ") g ON p.camper_id = g.camper_id " .
        "WHERE (";
    // to include overridden blocks
    for ($i = 0; $i < count($blockIdsOverride); $i++) {
        $sql .= " (g.block_id = " . $blockIdsOverride[$i] . " AND g.group_name = \"" . $chugGroups[$i] . "\")";
        if(count($blockIdsOverride) - $i > 1) {  // if there are more to add
            $sql .= " OR"; 
        }
        else { // last block!
            $sql .= ") ";
        }
    }
    $sql .= "GROUP BY p.camper_id, p.name, p.bunk, p.edah, p.rosh, p.roshphone, p.last ORDER BY p.bunk+0>0 DESC, p.bunk+0,LENGTH(p.bunk), p.last";

    $result = $dbc->doQuery($sql, $localErr);
    if ($result == false) {
        echo dbErrorString($sql, $localErr);
        exit();
    }

    // ***************************************************************************
    // ************************* Create Camper Schedules *************************
    // ***************************************************************************

    $allSchedules = "";

    // Call the function to automatically generate a schedule for each camper based on the results of the SQL query
    while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
        $schedule  = "<div class=\"container print-show schedule\" id=\"{$row[0]}\">";
        $schedule .= $scheduleTemplate; // set basic template

        // Now, replace all keywords with the info from the camper's row

        // 1. Manual ones we know to always expect (name, bunk, edah, rosh, roshphone)
        $schedule = str_replace("{{Name}}", $row[1] ?? "", $schedule); // name
        $schedule = str_replace("{{Bunk}}", $row[2] ?? "-", $schedule); // bunk
        $schedule = str_replace("{{" . ucfirst(edah_term_singular) . "}}", $row[3], $schedule); // edah
        $schedule = str_replace("{{Rosh}}", $row[4] ?? "", $schedule); // rosh
        $schedule = str_replace("{{Rosh Phone Number}}", $row[5] ?? "", $schedule); // roshphone

        // 2. Replace Chug/Perek Assignments
        for($i = 0; $i < count($chugGroups); $i++) {
            // $chugGroups has chug group names corresponding to the order the chugim are ordered in the 
            // SQL response, so can iterate through those lists simultaneously (and 6 fields are always expected)
            $schedule = str_replace("{{" . $chugGroups[$i] . "}}", $row[$i+6] ?? "", $schedule);
        }

        $schedule .= "</div>"; // close the div (or else we end up with them all nested!)
        $allSchedules .= $schedule; // and output the result!

        // 3. Also, save the name/camper_id to $camperId2Name
        $camperStr = isset($row[2]) ? $row[1] . " (Bunk: " . $row[2] . ")" : $row[1];
        $camperId2Name[$row[0]] = $camperStr;
    }
?>

<!-- Uses choices-js, more info at https://github.com/Choices-js/Choices -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {

            const elements = document.querySelectorAll('.choices-js');
            elements.forEach((element) =>  {
                const choices = new Choices(element, {
                    shouldSort: false,
                    allowHTML: true,
                    searchChoices: false,
                    removeItemButton: true,
                });
            });
        });
    </script>

<div class="card card-body mt-3 container print-hide">
    <div class="card card-body bg-light">
        <div class="page-header"><h2>Print Custom Schedules</h2></div>
        <p>Below, find the custom schedules generated for each camper. To save them or print them, just print this 
        page directly</p>
        <p><strong>TIP: </strong>Consider printing multiple camper schedules on one page of paper. Likely under the 
        "Advanced" or "More settings" for the print menu, change the number of pages per sheet. Then select how many 
        individual schedules you want per piece of paper.</p>
        <p><strong>TIP: </strong>Most browsers include a "Save to PDF" option from the print menu which will allow 
        you to save the set of schedules for later instead of printing them immediately.</p>
        <p><strong>TIP: </strong>Be sure to look at the "Print Preview" before printing - you may need to make 
        modifications to the template if a camper's schedule takes multiple pages.</p>
        <p><strong>TIP: </strong>Ensure double-sided printing is <u>OFF</u> when printing!</p>
        <div><button onClick="window.print()" class="btn btn-success">Print camper schedules!</button></div>
        
        <p class="mt-4">Use the dropdown below to filter and view schedules for specific campers (optional):</p>
        <select class="form-select bg-info choices-js" id="camper_list" name="campers[]" onchange="updateSchedules()" multiple>
            <?php echo genPickList($camperId2Name, array(), "campers"); ?>
        </select>
    </div>
</div>

<?php

    echo $allSchedules;

    echo footerText();
?>

<script>
    function updateSchedules() {
        // Get all selected values
        const selectedValues = Array.from(document.getElementById('camper_list').selectedOptions).map(option => option.value);

        // Toggle all schedules based on selection
        document.querySelectorAll('.schedule').forEach(el => {
            el.style.display = selectedValues.length > 0 ? 'none' : '';
        });

        // Show selected campers
        selectedValues.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = '';
        });
    }
</script>
</body>
