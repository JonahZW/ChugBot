<?php
session_start();
include_once 'addEdit.php';
include_once 'formItem.php';
bounceToLogin();
checkLogout();

$editEdahPage = new EditPage("Edit " . ucfirst(edah_term_singular),
    "Please update " . (edah_term_singular) . " information as needed",
    "edot", "edah_id");
$editEdahPage->addColumn("name");
$editEdahPage->addColumn("edah_group_id", false, true);
$editEdahPage->addColumn("rosh_name", false);
$editEdahPage->addColumn("rosh_phone", false);
$editEdahPage->addColumn("comments", false);
$editEdahPage->handleSubmit();

$nameField = new FormItemSingleTextField(ucfirst(edah_term_singular) . " Name", true, "name", 0);
$nameField->setInputClass("element text medium");
$nameField->setInputType("text");
$nameField->setInputMaxLength(255);
$nameField->setInputValue($editEdahPage->columnValue("name"));
$nameField->setError($editEdahPage->errForColName("name"));
$nameField->setGuideText("Choose your " . edah_term_singular . " name (Kochavim, Ilanot 1, etc.)");
$editEdahPage->addFormItem($nameField);

$edahGroupIdVal = $editEdahPage->columnValue("edah_group_id"); // May be NULL.
$edahGroupDropDown = new FormItemDropDown(ucfirst(edah_term_singular) . " Group", false, "edah_group_id", 1);
$edahGroupDropDown->setGuideText("Optionally, assign this " . edah_term_singular . " to a group");
$edahGroupDropDown->setError($editEdahPage->errForColName("edah_group_id"));
$edahGroupDropDown->setInputClass("element form-select medium");
$edahGroupDropDown->setInputSingular("edah Group");
$edahGroupDropDown->setColVal($edahGroupIdVal);
$edahGroupDropDown->fillDropDownId2Name($editEdahPage->dbErr,
    "edah_group_id", "edah_groups");
$editEdahPage->addFormItem($edahGroupDropDown);

$roshField = new FormItemSingleTextField("Rosh " . ucfirst(edah_term_singular) . " (head counselor) Name", false, "rosh_name", 2);
$roshField->setInputClass("element text medium");
$roshField->setInputType("text");
$roshField->setInputMaxLength(255);
$roshField->setInputValue($editEdahPage->columnValue("rosh_name"));
$roshField->setGuideText("Enter the head counselor name (optional)");
$editEdahPage->addFormItem($roshField);

$roshPhoneField = new FormItemSingleTextField("Rosh " . ucfirst(edah_term_singular) . " Phone", false, "rosh_phone", 3);
$roshPhoneField->setInputType("text");
$roshPhoneField->setInputClass("element text medium");
$roshPhoneField->setInputMaxLength(255);
$roshPhoneField->setInputValue($editEdahPage->columnValue("rosh_phone"));
$roshPhoneField->setGuideText("Phone number for the head counselor (optional)");
$editEdahPage->addFormItem($roshPhoneField);

$commentsField = new FormItemTextArea("Comments", false, "comments", 4);
$commentsField->setInputClass("element textarea medium");
$commentsField->setInputValue($editEdahPage->columnValue("comments"));
$commentsField->setGuideText("Comments about this " . ucfirst(edah_term_singular) . " (optional)");
$editEdahPage->addFormItem($commentsField);

$editEdahPage->renderForm();
