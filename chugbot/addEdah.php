<?php
session_start();
include_once 'addEdit.php';
include_once 'formItem.php';
bounceToLogin();
checkLogout();

$addEdahPage = new AddPage("Add " . ucfirst(edah_term_singular),
    "Please enter your " . (edah_term_singular) . " information",
    "edot", "edah_id");
$addEdahPage->addColumn("name");
$addEdahPage->addColumn("edah_group_id", false, true);
$addEdahPage->addColumn("rosh_name", false);
$addEdahPage->addColumn("rosh_phone", false);
$addEdahPage->addColumn("comments", false);
$addEdahPage->handleSubmit();

$nameField = new FormItemSingleTextField(ucfirst(edah_term_singular) . " Name", true, "name", 0);
$nameField->setInputType("text");
$nameField->setInputClass("element text medium");
$nameField->setInputMaxLength(255);
$nameField->setInputValue($addEdahPage->columnValue("name"));
$nameField->setError($addEdahPage->errForColName("name"));
$nameField->setGuideText("Choose your " . (edah_term_singular) . " name (Kochavim, Ilanot 1, etc.)");
$addEdahPage->addFormItem($nameField);

$edahGroupIdVal = $addEdahPage->columnValue("edah_group_id"); // May be NULL.
$edahGroupDropDown = new FormItemDropDown(ucfirst(edah_term_singular) . " Group", false, "edah_group_id", 1);
$edahGroupDropDown->setGuideText("Optionally, assign this " . edah_term_singular . " to a group");
$edahGroupDropDown->setError($addEdahPage->errForColName("edah_group_id"));
$edahGroupDropDown->setInputClass("element form-select medium");
$edahGroupDropDown->setInputSingular("edah Group");
$edahGroupDropDown->setColVal($edahGroupIdVal);
$edahGroupDropDown->fillDropDownId2Name($addEdahPage->dbErr,
    "edah_group_id", "edah_groups");
$addEdahPage->addFormItem($edahGroupDropDown);

$roshField = new FormItemSingleTextField("Rosh " . ucfirst(edah_term_singular) . " (head counselor) Name", false, "rosh_name", 1);
$roshField->setInputType("text");
$roshField->setInputClass("element text medium");
$roshField->setInputMaxLength(255);
$roshField->setInputValue($addEdahPage->columnValue("rosh_name"));
$roshField->setGuideText("Enter the head counselor name (optional)");
$addEdahPage->addFormItem($roshField);

$roshPhoneField = new FormItemSingleTextField("Rosh " . ucfirst(edah_term_singular) . " Phone", false, "rosh_phone", 2);
$roshPhoneField->setInputType("text");
$roshPhoneField->setInputClass("element text medium");
$roshPhoneField->setInputMaxLength(255);
$roshPhoneField->setInputValue($addEdahPage->columnValue("rosh_phone"));
$roshPhoneField->setGuideText("Phone number for the head counselor (optional)");
$addEdahPage->addFormItem($roshPhoneField);

$commentsField = new FormItemTextArea("Comments", false, "comments", 3);
$commentsField->setInputClass("element textarea medium");
$commentsField->setInputValue($addEdahPage->columnValue("comments"));
$commentsField->setGuideText("Comments about this " . ucfirst(edah_term_singular) . " (optional)");
$addEdahPage->addFormItem($commentsField);

$addEdahPage->renderForm();
