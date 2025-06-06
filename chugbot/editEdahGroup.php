<?php
session_start();
include_once 'addEdit.php';
include_once 'formItem.php';
bounceToLogin();
checkLogout();

$editEdahGroupPage = new EditPage("Edit " . ucfirst(edah_term_singular) . " Group",
    "Please enter " . edah_term_singular . " group information",
    "edah_groups", "edah_group_id");
$editEdahGroupPage->addColumn("name");
$editEdahGroupPage->handleSubmit();

$nameField = new FormItemSingleTextField(ucfirst(edah_term_singular) . " Name", true, "name", 0);
$nameField->setInputType("text");
$nameField->setInputClass("element text medium");
$nameField->setInputMaxLength(255);
$nameField->setInputValue($editEdahGroupPage->columnValue("name"));
$nameField->setError($editEdahGroupPage->errForColName("name"));
$nameField->setGuideText("Enter a name for the group of " . edah_term_plural);
$editEdahGroupPage->addFormItem($nameField);

$editEdahGroupPage->renderForm();
