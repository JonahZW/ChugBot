<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Edit Camper Info</title>
<link rel="stylesheet" type="text/css" href="meta/view.css" media="all">
<script type="text/javascript" src="meta/view.js"></script>

</head>

<?php
    session_start();
    include 'functions.php';?>

<?php

    // Define variables and set to empty values.
    $edah_id = $session_id = $first = $last = $email = $needs_first_choice = $inactive = $camper_id = "";
    $edahErr = $sessionErr = $nameErr = $emailErr = $camperIdErr = $dbErr = "";
    $submitData = FALSE;
    $fromAddPage = FALSE;
    $fromHome = FALSE;
    
    // Connect to the database.
    $mysqli = connect_db();
    
    // Grab edot, and sessions.
    $edahId2Name = array();
    $sessionId2Name = array();
    fillId2Name($mysqli, $edahId2Name, $dbErr,
                "edah_id", "edot");
    fillId2Name($mysqli, $sessionId2Name, $dbErr,
                "session_id", "sessions");
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (! empty($_POST["fromAddPage"])) {
            $fromAddPage = TRUE;
        }
        if (! empty($_POST["submitData"])) {
            $submitData = TRUE;
        }
        if (! empty($_POST["fromHome"])) {
            $fromHome = TRUE;
        }
        $edah_id = test_input($_POST["edah_id"]);
        $session_id = test_input($_POST["session_id"]);
        $first = test_input($_POST["first"]);
        $last = test_input($_POST["last"]);
        $email = test_input($_POST["email"]);
        $needs_first_choice = test_input($_POST["needs_first_choice"]);
        $inactive = test_input($_POST["inactive"]);
        $camper_id = test_input($_POST["camper_id"]);
        if (empty($needs_first_choice)) {
            $needs_first_choice = ""; // Default.
        }
        if (empty($inactive)) {
            $inactive = ""; // Default.
        }
        if (empty($camper_id)) {
            $camperIdErr = errorString("The edit page requires a camper ID");
        }
        // If we're coming from the add or home page, we get all parameters
        // from the ID.
        if (($fromAddPage || $fromHome) &&
            empty($camperIdErr)) {
            $camperIdNum = intval($camper_id);
            $sql = "select * from campers where camper_id = $camperIdNum";
            $result = $mysqli->query($sql);
            if ($result == FALSE) {
                $dbErr = dbErrorString($sql, $mysqli->error);
            } else if ($result->num_rows != 1) {
                $camperIdErr = errorString("camper ID $camper_id not found");
            } else {
                $row = $result->fetch_array(MYSQLI_NUM);
                $edah_id = $row[1];
                $session_id = $row[2];
                $first = $row[3];
                $last = $row[4];
                $email = $row[5];
                $needs_first_choice = $row[6];
                $active = $row[7];
            }
            mysqli_free_result($result);
        }
        // For required inputs, throw an error if not present.
        if (empty($edah_id)) {
            $edahErr = errorString("edah is required");
        }
        if (empty($session_id)) {
            $sessionErr = errorString("session is required");
        }
        if (empty($first) || empty($last)) {
            $nameErr = errorString("please provide first and last name");
        }
        if (empty($email)) {
            $emailErr = errorString("please provide an email address for confirmation of choices.");
        } else if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = errorString("$email is not a valid email address");
        }
        
        if (empty($nameErr) && empty($emailErr) && empty($sessionErr) &&
            empty($edahErr) && empty($camperIdErr) && empty($dbErr)) {
            // Convert string to int as needed, and update.
            $sessionNum = intval($session_id);
            $edahNum = intval($edah_id);
            $camperIdNum = intval($camper_id);
            $needsFirstChoiceNum = 0;
            if (! empty($needs_first_choice)) {
                $needsFirstChoiceNum = 1;
            }
            $activeNum = 1;
            if (! empty($inactive)) {
                $activeNum = 0;
            }
            
            $homeAnchor = homeAnchor();
            
            if ($submitData == TRUE) {
                // Insert edited data.
                $sql =
                "UPDATE campers SET edah_id = $edahNum, " .
                "session_id = $sessionNum, first = \"$first\", last = \"$last\", " .
                "email = \"$email\", needs_first_choice = $needsFirstChoiceNum, active = $activeNum " .
                "WHERE camper_id = $camperIdNum";
                $submitOk = $mysqli->query($sql);
                if ($submitOk == FALSE) {
                    echo(dbErrorString($sql, $mysqli->error));
                } else {
                    // TODO: Add link back to admin home.
                    echo("<h3>$first $last updated!  Please edit below if needed, or return $homeAnchor.</h3>");
                }
            } else if ($fromAddPage) {
                echo "<h3>$first $last added successfully!  Please edit below if needed, or return $homeAnchor.</h3>";
            }
        }
    }
    
    $mysqli->close();
?>

<body id="main_body" >

<?php
    echo $dbErr;
    ?>

<img id="top" src="meta/top.png" alt="">
<div id="form_container">

<h1><a>Edit Camper Info</a></h1>
<form id="form_1063606" class="appnitro" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
<div class="form_description">
<h2>Edit Camper Info</h2>
<p>(<font color="red">*</font> = required field).</p>
</div>
<ul>

<li id="li_1" >
<label class="description"><font color="red">*</font> Name</label>
<span>
<input id="first" name="first" class="element text" maxlength="255" size="16" value="<?php echo $first;?>"/>
<label>First</label>
</span>
<span>
<input id="last" name="last" class="element text" maxlength="255" size="24" value="<?php echo $last;?>"/>
<label>Last</label>
</span>
<span class="error"><?php echo $nameErr;?></span>
</li>
<li id="li_3" >
<label class="description" for="email"><font color="red">*</font> Email</label>
<div>
<input id="email" name="email" class="element text medium" type="text" maxlength="255" value="<?php echo $email;?>"/>
<span class="error"><?php echo $emailErr;?></span>
</div>
<p class="guidelines" id="guide_3"><small>Choose an email address for confirmation (<b>you can use the same email for more than one camper</b>).</small></p>
</li>

<li id="li_5" >
<label class="description" for="session_id"><font color="red">*</font> Session</label>
<div>
<select class="element select medium" id="session_id" name="session_id">
<?php
    echo genPickList($sessionId2Name, $session_id, "session");
    ?>
</select>
<span class="error"><?php echo $sessionErr;?></span>
</div><p class="guidelines" id="guide_5"><small>Choose your camp session.</small></p>
</li>

<li id="li_7" >
<label class="description" for="session_id"><font color="red">*</font> Edah</label>
<div>
<select class="element select medium" id="edah_id" name="edah_id">
<?php
    echo genPickList($edahId2Name, $edah_id, "edah");
    ?>
</select>
<span class="error"><?php echo $edahErr;?></span>
</div><p class="guidelines" id="guide_7"><small>Choose your edah for this summer!</small></p>
</li>

<?php
    // If we're a logged-in admin user, display options to toggle the "active" and
    // "needs_first_choice" inputs.
    $checkedText = "";
    if (! empty($needs_first_choice)) {
        $checkedText = "checked=\"checked\"";
    }
    $needsFirstChoiceForm = <<<HERE
<li>
<div>
<input id="needs_first_choice" name="needs_first_choice" type="checkbox" $checkedText>
<label for="needs_first_choice">Needs first choice</label>
<p class="guidelines" id="needs_first_choice_guide"><small>Check this box if this camper should always get their first choice of chug.</small></p>
</div>
</li>
HERE;
    if (isset($_SESSION['admin_logged_in'])) {
        echo "<h3>Administrative Settings</h3>";
        echo $needsFirstChoiceForm;
    }
    
    $checkedText = "";
    if (! empty($inactive)) {
        $checkedText = "checked=\"checked\"";
    }
    $inactiveForm = <<<HERE
<li>
<div>
<input id="inactive" name="inactive" type="checkbox" $checkedText>
<label for="active">Inactive</label>
<p class="guidelines" id="active_guide"><small>If you check this box, this camper will not be assigned.</small></p>
</div>
</li>
HERE;
    if (isset($_SESSION['admin_logged_in'])) {
        echo $inactiveForm;
    }
    ?>

<li class="buttons">
<input type="hidden" name="form_id" value="1063606" />

<input id="saveForm" class="button_text" type="submit" name="submit" value="Submit" />
</li>
</ul>
<input type="hidden" id="camper_id" name="camper_id" value="<?php echo $camper_id;?>"/>
<input type="hidden" name="submitData" value="1">
</form>
<div id="footer">
<?php
    echo footerText();
    ?>
</div>
</div>
<img id="bottom" src="meta/bottom.png" alt="">
</body>
</html>
