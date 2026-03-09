<?php
include_once '../dbConn.php';
include_once '../functions.php';
session_start();
checkLogout();

// if not admin, redirect to appropriate home page
if(!adminLoggedIn()) {
    if(roshLoggedIn()) {
        $roshUrl = urlIfy("../attendance/roshHome.php");
        header("Location: $roshUrl");
        exit();
    }
    else if(chugLeaderLoggedIn()) {
        $chugLeaderUrl = urlIfy("../attendance/chugLeaderHome.php");
        header("Location: $chugLeaderUrl");
        exit();
    }
    else if(camperLoggedIn()) {
        $camperUrl = urlIfy("../camperHome.php");
        header("Location: $camperUrl");
        exit();
    }
    else {
        $baseUrl = baseUrl();
        header("Location: $baseUrl");
        exit();
    }
}


$dbErr = "";
$sessionId2Name = array();
$blockId2Name = array();
$groupId2Name = array();
$edahId2Name = array();
$chugId2Name = array();
$edahGroupId2Name = array();
$bunkId2Name = array();
$edahGroup2group2ActiveBlock = array();
$edahId2edahGroupId = array();
$edahGroupId2edahId = array();

fillId2Name(null, $chugId2Name, $dbErr, "chug_id", "chugim", "group_id", "chug_groups");
fillId2Name(null, $sessionId2Name, $dbErr, "session_id", "sessions");
fillId2Name(null, $blockId2Name, $dbErr, "block_id", "blocks");
fillId2Name(null, $groupId2Name, $dbErr, "group_id", "chug_groups");
fillId2Name(null, $edahId2Name, $dbErr, "edah_id", "edot");
fillId2Name(null, $edahGroupId2Name, $dbErr, "edah_group_id", "edah_groups");
fillId2Name(null, $bunkId2Name, $dbErr, "bunk_id", "bunks");


setup_camp_specific_terminology_constants();

echo headerText("Attendance Admin");


// connect edah_ids to edah_group_ids
$db = new dbConn();
$db->addSelectColumn("edah_id");
$db->addSelectColumn("edah_group_id");
$result = $db->simpleSelectFromTable("edot", $err);
while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
    // row[0] is edah_id, row[1] is edah_group_id
    // if no edah group is assigned, set to -1
    if (is_null($row[1])) {
        $row[1] = -1;
        $edahGroupId2Name[-1] = "Other " . ucfirst(edah_term_plural) . " (No " . edah_term_singular . " group assigned)"; // extra handler
    }
    $edahId2edahGroupId[$row[0]] = $row[1];
    // and make sure there is an empty array for the group in $edahGroup2group2ActiveBlock
    $edahGroup2group2ActiveBlock[$row[1]] = array();

    // also connect edah group to edah
    if (!isset($edahGroupId2edahId[$row[1]])) {
        $edahGroupId2edahId[$row[1]] = array();
    } 
    array_push($edahGroupId2edahId[$row[1]], $row[0]);
}

// update saved attendance
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // determine what is being updated/set
    if(test_post_input("form") == "active-blocks") {
        // get list of edah group x chug group pairs from POST data
        $edahGroupXchugGroups = array_keys($_POST);
        array_pop($edahGroupXchugGroups);

        // build SQL - update edot_for_group one edah group at a time
        $anySet = FALSE;
        $sql = "UPDATE edot_for_group SET active_block_id = ( CASE ";
        $prevEdahGroup = NULL;
        // loop through all edah group/chug group pairs
        foreach($edahGroupXchugGroups as $idPair) {
            $block = test_post_input($idPair);
            if (!empty($block)) {
                $parts = explode("_", $idPair);
                // if this is a different edah group than last iteration, update the table for those previous edot
                if ($parts[0] != $prevEdahGroup && isset($prevEdahGroup)) {
                    if ($anySet) {
                        $sql .= "ELSE (NULL) END ) WHERE edah_id IN (" . implode(",",$edahGroupId2edahId[$prevEdahGroup]) . ")";
                    } else {
                        $sql = "UPDATE edot_for_group SET active_block_id = NULL WHERE edah_id IN (" . implode(",",$edahGroupId2edahId[$prevEdahGroup]);
                    }
                    // update table
                    $db = new dbConn();
                    $err = "";
                    $result = $db->doQuery($sql, $err);
                    if ($result == false) {
                        echo dbErrorString($sql, $err);
                        exit();
                    }
                    // reset sql
                    $sql = "UPDATE edot_for_group SET active_block_id = ( CASE ";
                }
                $sql .= "WHEN (group_id = " . $parts[1] . ") THEN $block ";
                $anySet = TRUE;
                $prevEdahGroup = $parts[0];
            }
        }
        if ($anySet) {
            $sql .= "ELSE (NULL) END ) WHERE edah_id IN (" . implode(",",$edahGroupId2edahId[$prevEdahGroup]) . ")";
        } else {
            $sql = "UPDATE edot_for_group SET active_block_id = NULL WHERE edah_id IN (" . implode(",",$edahGroupId2edahId[$prevEdahGroup]);
        }
        // update table
        $db = new dbConn();
        $err = "";
        $result = $db->doQuery($sql, $err);
        if ($result == false) {
            echo dbErrorString($sql, $err);
            exit();
        }
        
        // success!
        $successMessage = "<div class=\"col-md-6 offset-md-3\"><div class=\"alert alert-success alert-dismissible fade show m-2\" role=\"alert\">" . 
        "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button><h5>Active " . ucfirst(block_term_plural) .
        " Successfully Updated</h5>People taking and viewing attendance will now see the " . chug_term_singular . " for the newly set " . block_term_plural . "</div></div>";
        echo $successMessage;
            
    }
    else if (test_post_input("form") == "purge") {
        // delete old records

        // 1: verify date is valid form
        $dateInput = test_post_input("date");
        $fullDate = strtotime($dateInput);
        $date = date('Y-m-d', $fullDate);
        if($fullDate == "") {
            echo "<div class=\"col-md-6 offset-md-3\"><div class=\"alert alert-danger alert-dismissible fade show m-2\" role=\"alert\">" . 
            "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button><h5><strong>Error:</strong> " . 
            "Invalid Date</h5>An invalid date was provided. Please try again to purge old attendance records</div></div>";
            exit();
        }
        else {
            // 2: do deletions
            $localErr = "";
            $dbc = new DbConn();
            // 2a: delete attendance records
            $sql = "DELETE FROM attendance WHERE date < '$date'";
            $result = $dbc->doQuery($sql, $localErr);
            if ($result == false) {
                echo dbErrorString($sql, $localErr);
                exit();
            }
            // 2b: delete that attendance was taken for that perek
            $sql = "DELETE FROM attendance_block_by_date WHERE date < '$date'";
            $result = $dbc->doQuery($sql, $localErr);
            if ($result == false) {
                echo dbErrorString($sql, $localErr);
                exit();
            }
            // show success message
            $successMessage = "<div class=\"col-md-6 offset-md-3\"><div class=\"alert alert-success alert-dismissible fade show m-2\" role=\"alert\">" . 
            "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button><h5>Old Attendance Records Successfully Purged</h5>" .
            "The earliest day with saved attendance records is <strong>" . date("D F j, Y", strtotime($date)) . "</strong></div></div>";
            echo $successMessage;
        }
    }
}

// get current edah group -> chug group -> active block matches
$db = new dbConn();
$err = "";
$db->addSelectColumn("edah_id");
$db->addSelectColumn("group_id");
$db->addSelectColumn("active_block_id");
$result = $db->simpleSelectFromTable("edot_for_group", $err);
if ($result == false) {
    echo dbErrorString($sql, $localErr);
    exit();
}
while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
    // row[0] is edah id, row[1] is group id, row[2] is active block id
    // first will find the proper edah group
    $edahGroup = $edahId2edahGroupId[$row[0]];
    // then store the chug group and active block
    $edahGroup2group2ActiveBlock[$edahGroup][$row[1]] = array($row[2]=>$row[2]);
}

?>

<div class="card card-body mt-3 mb-3 container">
<h1><a>Welcome</a></h1>
<div class="page-header">
<h2>Chug Attendance Admin Page</h3>
</div>


    <form class="card card-body bg-light mb-3" id="assign_active_block_form" method="POST" action=""><ul>
        <h5>Assign Active <?php echo ucfirst(block_term_plural);?></h5>
        <p>Campers have different <?php echo chug_term_singular;?> assignments during different parts of the summer. To use the attendance system, 
        administrators are responsible for assigning the current <?php echo block_term_singular;?> for each <?php echo chug_term_singular;?> group.</p>
        <p>If <?php echo edah_term_plural;?> have been assigned to multiple "<?php echo edah_term_singular;?> groups," those groups show below in an accordion, 
        and dropdowns for each <?php echo chug_term_singular;?> group can be found within. If no "<?php echo edah_term_singular;?> groups" are set, the dropdowns display normally.</p>
        <p>Use these dropdowns to assign which <?php echo block_term_singular;?> is active. This determines which campers the <?php echo chug_term_singular;?> 
        leaders will see when taking attendance, as well as the assignments a Rosh/Yoetzet will see when reviewing it.</p>
        <p>Not selecting a <?php echo block_term_singular;?> (or setting it to <code>-- Choose <?php echo ucfirst(block_term_singular);?> --</code>) for a group 
        makes the <?php echo chug_term_singular;?> group unavailable for taking/reviewing attendance.</p>
        <p>Leaving every <?php echo chug_term_singular;?> perek unset disables the attendance system for <?php echo chug_term_singular;?> leaders and Roshes/Yoetzot.</p>
        <?php 

            // make an accordion for each edah group, then a dropdown within to make the actual assignments
            // if only one edah group, do not make an accordion and leave flush
            $isAccordion = sizeof($edahGroup2group2ActiveBlock) > 1;
            $edahGroupHtml = "";

            if ($isAccordion) {
                $edahGroupAccordion = new bootstrapAccordion($name="edahGroups", $flush=false, $alwaysOpen=true);
            }

            // loop through all edah groups
            foreach($edahGroupId2Name as $edahGroupId => $edahGroupName) {
                $edahGroupStr = "";
                foreach($groupId2Name as $groupId => $groupName) {
                    // make sure this group is available for this edah group
                    if (isset($edahGroup2group2ActiveBlock[$edahGroupId][$groupId])) {
                        $edahGroupStr .= "<li><label class=\"description\" for=\"edah{$edahGroupId}_group{$groupId}\">$groupName</label>";
                        $edahGroupStr .= "<div id=\"edah{$edahGroupId}_group{$groupId}_select\" class=\"pb-2\"><select class=\"form-select\" id=\"edah{$edahGroupId}_group{$groupId}\" name=\"{$edahGroupId}_{$groupId}\">";
                        $edahGroupStr .= genPickList($blockId2Name, $edahGroup2group2ActiveBlock[$edahGroupId][$groupId], block_term_singular);
                        $edahGroupStr .= "</select></div></li>";
                    }
                }

                // add to accordion
                if ($isAccordion) {
                    if ($edahGroupStr != "") {
                        $edahGroupAccordion->addAccordionElement($id="eg" . $edahGroupId, $title=$edahGroupName, $body=$edahGroupStr, $open=false);
                    }
                } else {
                    $edahGroupHtml .= $edahGroupStr;
                }
            }
            echo $isAccordion ? str_replace("\"accordion-button", "\"accordion-button bg-secondary text-white", $edahGroupAccordion->renderHtml()) : $edahGroupHtml;

        ?>
        <li>
            <button class="btn btn-success mt-3" type="submit" id="submit_btn" name="form" value="active-blocks">Save Active <?php echo ucfirst(block_term_plural);?></button>
        </li>
    </ul></form>

    <form class="card card-body bg-light" id="purge_records_form" method="POST" action=""><ul>
        <h5>Purge Old Attendance Records</h5>
        <p>If historical attendance records are no longer needed, you can delete all records before a provided date (not inclusive - records will be kept for the day submitted below, but not before).</p>
        <p><strong style="color:red;">Warning:</strong> once this is submitted, it cannot be undone. Purged attendance records cannot be recovered.</p>
        <li>
            <label class="description" for="date"><span style="color:red;">*</span>Delete Attendance Records Before:</label>
            <div id="date_pick" class="pb-2">
                <input type="date" id="date" name="date" class="form-control medium" onchange="dateChanged()" required>
            </div>
        </li>
        <li>
            <button id="purge-modal-btn" type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#purgeModal" disabled>Purge</button>
        </li>
    </form>
</div>


<div class="modal fade" id="purgeModal" tabindex="-1" aria-labelledby="purgeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title fs-5" id="purgeModalLabel">Warning</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to purge attendance records before <strong id="purge-date-modal" style="color:red"></strong>? There is no way to undo this action.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="modal-submit" type="submit" class="btn btn-danger" form="purge_records_form" value="purge" name="form" disabled>Yes, I'm Sure - Delete Records</button>
      </div>
    </div>
  </div>
</div>

<script>
// when date is changed in the date picker, enable "purge" functions - activate the warning button and submit button, and
// autofill confirmation window with the date which will have everything before it deleted
function dateChanged() {
    document.getElementById("purge-modal-btn").disabled = true;
    document.getElementById("modal-submit").disabled = true;
    var date = new Date(document.getElementById("date").value + 'T00:00:00');
    if(date != "Invalid Date") {
        document.getElementById("purge-modal-btn").disabled = false;
        document.getElementById("purge-date-modal").innerHTML = date.toLocaleDateString("en-us", { weekday:"long", year:"numeric", month:"short", day:"numeric"});
        document.getElementById("modal-submit").disabled = false;
    }
}

</script>

<?php
echo footerText();
?>

</body>
</html>
