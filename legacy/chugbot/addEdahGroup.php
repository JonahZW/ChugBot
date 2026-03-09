<?php
session_start();
include_once 'addEdit.php';
include_once 'formItem.php';
bounceToLogin();
checkLogout();

$addEdahGroupPage = new AddPage("Add " . ucfirst(edah_term_singular) . " Group",
    "Please enter " . edah_term_singular . " group information",
    "edah_groups", "edah_group_id");
$addEdahGroupPage->addColumn("name");
$addEdahGroupPage->handleSubmit();

$nameField = new FormItemSingleTextField(ucfirst(edah_term_singular) . " Name", true, "name", 0);
$nameField->setInputType("text");
$nameField->setInputClass("element text medium");
$nameField->setInputMaxLength(255);
$nameField->setInputValue($addEdahGroupPage->columnValue("name"));
$nameField->setError($addEdahGroupPage->errForColName("name"));
$nameField->setGuideText("Enter a name for the group of " . edah_term_plural);
$addEdahGroupPage->addFormItem($nameField);

$addEdahGroupPage->renderForm();
