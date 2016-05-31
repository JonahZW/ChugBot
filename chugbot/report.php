<?php
    session_start();
    include_once 'dbConn.php';
    include_once 'functions.php';
    include_once 'formItem.php';
    require_once 'fpdf/fpdf.php';
    bounceToLogin();
    
    abstract class OutputTypes {
        const Html = 0;
        const Pdf = 1;
        const Csv = 2;
    }
    
    // A class to generate a printable PDF report.
    class PDF extends FPDF {
        public function GenTable($title, $header, $data) {
            // Split line break in title into two lines.
            $titleParts = explode("<br>", $title);
            $this->SetTitle($titleParts[0]);
            // Colors, line width and bold font
            $this->SetTextColor(128, 128, 128);
            $this->SetLineWidth(.3);
            $this->SetFont('Arial','B',$this->fontSize);
            foreach ($titleParts as $titleLine) {
                $this->Cell(0, 10, $titleLine, 0, 2, 'C');
            }
            if ($this->reportedItem) {
                $forText = "Report for " . $this->typeOfReport . " " . $this->reportedItem;
                $this->Cell(0, 10, $forText, 0, 2, 'C');
            }
            $this->SetFillColor(128, 128, 128);
            $this->SetTextColor(255);
            $this->SetDrawColor(128,0,0);
           
            // Header
            for ($i = 0; $i < count($header); $i++) {
                $this->Cell($this->columnWidths[$i], 7, $header[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            // Color and font restoration
            $this->SetFillColor(224, 235, 255);
            $this->SetTextColor(0);
            $this->SetFont('');
            // Data
            $fill = false;
            $sz = 5;
            foreach ($data as $row) {
                // Calculate the height of the row.
                $nb = 0;
                for($i = 0; $i < count($row); $i++) {
                    $nb = max($nb,
                              $this->NbLines($this->columnWidths[$i], $row[$i]));
                }
                $h = $sz * $nb;
                // Issue a page break first if needed.
                $this->CheckPageBreak($h);
                // Draw the cells of the row.
                for ($i = 0; $i < count($row); $i++) {
                    $alignment = ($i <= count($row) / 2) ? 'L' : 'R';
                    $x = $this->GetX();
                    $y = $this->GetY();
                    $w = $this->columnWidths[$i];
                    $this->Rect($x,$y,$w,$h);
                    $this->MultiCell($w, $sz, $row[$i],
                                     0,//'LR',
                                     $alignment,
                                     0); //$fill);
                    $this->SetXY($x + $w, $y);
                }
                $this->Ln($h);
                $fill = !$fill;
            }
            // Closing line
            $this->Cell(array_sum($this->columnWidths), 0, '', 'T');
        }
        
        function CheckPageBreak($h) {
            // If the height h would cause an overflow, add a new page immediately.
            if ($this->GetY()+$h>$this->PageBreakTrigger) {
                $this->AddPage($this->CurOrientation);
            }
        }
        
        function NbLines($w, $txt)
        {
            // Computes the number of lines a MultiCell of width w will take
            $cw=&$this->CurrentFont['cw'];
            if($w==0)
                $w=$this->w-$this->rMargin-$this->x;
            $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
            $s=str_replace("\r",'',$txt);
            $nb=strlen($s);
            if($nb>0 and $s[$nb-1]=="\n")
                $nb--;
            $sep=-1;
            $i=0;
            $j=0;
            $l=0;
            $nl=1;
            while($i<$nb)
            {
                $c=$s[$i];
                if($c=="\n")
                {
                    $i++;
                    $sep=-1;
                    $j=$i;
                    $l=0;
                    $nl++;
                    continue;
                }
                if($c==' ')
                    $sep=$i;
                $l+=$cw[$c];
                if($l>$wmax)
                {
                    if($sep==-1)
                    {
                        if($i==$j)
                            $i++;
                    }
                    else
                        $i=$sep+1;
                    $sep=-1;
                    $j=$i;
                    $l=0;
                    $nl++;
                }
                else
                    $i++;
            }
            return $nl;
        }
        
        public function setColWidths($w) {
            $this->columnWidths = $w;
        }
        
        public function setFontSize($s) {
            $this->fontSize = $s;
        }
        
        public function setReportTypeAndNameOfItem($t, $n) {
            $this->typeOfReport = $t;
            $this->reportedItem = $n;
        }
        
        private $columnWidths = NULL;
        private $fontSize = 12;
        private $typeOfReport = NULL;
        private $reportedItem = NULL;
    }
    
    // A class to generate a zebra-striped report.
    class ZebraReport {
        function __construct($db, $sql, $outputType) {
            $this->db = $db;
            $this->sql = $sql;
            $this->outputType = $outputType;
        }
        
        public function addIgnoreColumn($ic) {
            $this->ignoreCols[$ic] = 1;
        }
        
        public function addNewTableColumn($ntc) {
            $this->newTableColumns[$ntc] = TRUE;
        }
        
        public function setCaption($c) {
            $this->caption = $c;
        }
        
        public function addCaptionReplaceColKey($key, $column, $default) {
            $this->captionReplaceColKeys[$key] = $column;
            $this->captionReplaceColDefault[$key] = $default;
        }
        
        public function setIdCol2EditPage($idCol, $editPage, $valCol) {
            $this->idCol2EditUrl[$idCol] = urlIfy($editPage);
            $this->valCol2IdCol[$valCol] = $idCol;
        }
        
        public function setIdNameMap($idNameMap, $regex = NULL) {
            $this->idNameMap = $idNameMap;
            $this->idNameMapRegex = $regex;
        }
        
        public function addIdNameMapCol($col) {
            $this->idNameMapCols[$col] = TRUE;
        }
    
        private function shouldSkipColumn($col) {
            if (array_key_exists($col, $this->newTableColumns)) {
                return TRUE; // Don't re-display new table columns.
            }
            if (array_key_exists($col, $this->idCol2EditUrl)) {
                return TRUE; // Don't display ID columns.
            }
            if (array_key_exists($col, $this->ignoreCols)) {
                return TRUE;
            }
            
            return FALSE;
        }
        
        public function setReportTypeAndNameOfItem($t, $n) {
            $this->typeOfReport = $t;
            $this->reportedItem = $n;
        }
        
        public function setPdfFontSizeAndMult($size, $mult) {
            $this->pdfFontSize = $size;
            $this->mult = $mult;
        }
        
        public function needToStartNewTable($newTableColumnValues, $row) {
            if (empty($newTableColumnValues)) {
                // If the new-table values are not set, it's our first time
                // through the loop, and we should always create a new table.
                return TRUE;
            }
            foreach ($newTableColumnValues as $col => $val) {
                if ($row[$col] != $val) {
                    // If any new-table column value has changed from its
                    // previous version, we need a new table.
                    return TRUE;
                }
            }
            
            // None of the new-table columns has changed.
            return FALSE;
        }
        
        public function renderTable() {
            if ($this->sql == NULL) {
                echo genFatalErrorReport(array("No table query was specified"));
                exit();
            }
            $err = "";
            $result = $this->db->doQuery($this->sql, $err);
            if ($result == FALSE) {
                echo dbErrorString($this->sql, $err);
                exit();
            }
            // If no rows were found, display a message.
            if ($result->num_rows == 0) {
                echo "<h3>No matching assignments were found.</h3>";
                return;
            }
            // Step through the results, build the table, and display it.  We
            // start with a header, and then display zebra-striped rows.  If we
            // have a $newDivColumn, we create a new table section each
            // time that column's value changes.
            // We also build PDF tables, in case we've been asked to generate printable
            // output, and prepare to emit CSV if requested.
            $pdfTables = array();
            $pdf = new PDF('Landscape', 'mm', 'A4');
            $pdf->setReportTypeAndNameOfItem($this->typeOfReport, $this->reportedItem);
            $pdf->setFontSize($this->pdfFontSize);
            $pdfHeader = array();
            $pdfData = array();
            $pdfDataRow = array();
            $pdfCaptionText = "";
            $pdfColWidths = array();
            $html = "";
            $newTableColumnValues = array();
            $rowIndex = 0;
            if ($this->outputType == OutputTypes::Csv) {
                // Output headers so that the CSV is downloaded rather than displayed.
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=ChugReport.csv');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                // Create a file pointer connected to the output stream.
                $output = fopen('php://output', 'w');
            }
            while ($row = $result->fetch_assoc()) {
                if ($this->needToStartNewTable($newTableColumnValues, $row)) {
                    if (! empty($newTableColumnValues)) {
                        // If we have a changed new table value, close the div and
                        // previous table before starting a new one.
                        $html .= "</div></table>";
                        // Add the table we just built to the PDF.
                        $pdf->AddPage();
                        $pdf->setColWidths($pdfColWidths);
                        $pdf->GenTable($pdfCaptionText, $pdfHeader, $pdfData);
                        // Re-initialize the PDF header and data arrays.  If we have
                        // CSV output, write the title, column headers, and data of
                        // the table we just built.
                        if ($this->outputType == OutputTypes::Csv) {
                            $titleParts = explode("<br>", $pdfCaptionText);
                            $csvTitle = $titleParts[0] . ": " . $this->typeOfReport . " " . $this->reportedItem;
                            fputcsv($output, array($csvTitle));
                            fputcsv($output, $pdfHeader);
                            foreach ($pdfData as $pdfRow) {
                                fputcsv($output, $pdfRow);
                            }
                        }
                    }
                    $html .= "<div class=zebra><table>";
                    $pdfHeader = array();
                    $pdfData = array();
                    $pdfColWidths = array();
                    if ($this->caption) {
                        $captionText = $this->caption;
                        $pdfCaptionText = $this->caption;
                        $i = 0;
                        foreach ($this->newTableColumns as $ntc => $val) {
                            // If we have a new table column, create an edit link if requested.
                            $replaceText = "LINK" . "$i";
                            if (array_key_exists($ntc, $this->valCol2IdCol)) {
                                $idCol = $this->valCol2IdCol[$ntc];
                                $idVal = $row[$idCol];
                                $d = $row[$ntc];
                                if ($idVal) {
                                    $editUrl = $this->idCol2EditUrl[$idCol] . "?eid=$idVal";
                                    $linkText = "<a href=\"$editUrl\">$d</a>";
                                    $captionText = str_replace($replaceText, $linkText, $captionText);
                                    $pdfCaptionText = str_replace($replaceText, $d, $pdfCaptionText);
                                } else {
                                    $captionText = str_replace($replaceText, $d, $captionText);
                                    $pdfCaptionText = str_replace($replaceText, $d, $pdfCaptionText);
                                }
                            }
                            $i++;
                        }
                        // Loop through the caption text words, and check for
                        // any that appear in captionReplaceColKeys.  For any
                        // such values, replace the string with the corresponding
                        // value of $row.  If we did not get a value back, use the default.
                        foreach ($this->captionReplaceColKeys as $key => $column) {
                            $replaceText = $row[$column];
                            if (! $replaceText) {
                                $replaceText = $this->captionReplaceColDefault[$key];
                            }
                            $captionText = str_replace($key, $replaceText, $captionText);
                            $pdfCaptionText = str_replace($key, $replaceText, $pdfCaptionText);
                        }
                        $html .= "<caption>$captionText</caption>";
                    }
                    $html .= "<tr>";
                    
                    // Use the column keys as table headers.
                    $colKeys = array_keys($row);
                    foreach ($colKeys as $tableHeader) {
                        if ($this->shouldSkipColumn($tableHeader)) {
                            continue;
                        }
                        $html .= "<th>$tableHeader</th>";
                        array_push($pdfHeader, $tableHeader);
                        // Initialize the width for the column corresponding to this
                        // header.
                        array_push($pdfColWidths, (strlen($tableHeader) * $this->mult) + $this->add);
                    }
                    $html .= "</tr>";
                    
                    // Update new table column values.
                    foreach ($this->newTableColumns as $ntc => $val) {
                        $newTableColumnValues[$ntc] = $row[$ntc];
                    }
                    $rowIndex = 0; // Reset row index, so each table gets its own zebra striping.
                }
                // Compute stripe color, and add table data.
                $oddText = "";
                if ($rowIndex++ % 2 != 0) {
                    $oddText = "class=zebradarkstripe";
                }
                $html .= "<tr $oddText>";
                $i = 0;
                foreach ($colKeys as $tableDataKey) {
                    if ($this->shouldSkipColumn($tableDataKey)) {
                        continue;
                    }
                    // If we have an ID value corresponding to this table key,
                    // display the table data as a link to the edit page.
                    $d = $row[$tableDataKey];
                    $tableData = "";
                    if (array_key_exists($tableDataKey, $this->valCol2IdCol)) {
                        $idCol = $this->valCol2IdCol[$tableDataKey];
                        $idVal = $row[$idCol];
                        if ($idVal) {
                            $editUrl = $this->idCol2EditUrl[$idCol] . "?eid=$idVal";
                            $tableData = "<a href=\"$editUrl\">$d</a>";
                        } else {
                            $tableData = $d;
                        }
                    } else {
                        $tableData = $d;
                        // If this column value should be looked up, do so.
                        if (array_key_exists($tableDataKey, $this->idNameMapCols) &&
                            array_key_exists($tableData, $this->idNameMap)) {
                            $tableData = $this->idNameMap[$tableData];
                            if ($this->idNameMapRegex) {
                                $pattern = "/" . $this->idNameMapRegex . ".*$/";
                                $tableData = preg_replace($pattern, "", $tableData);
                            }
                        }
                    }
                    $html .= "<td>$tableData</td>";
                    // Look up and replace the PDF/report column.
                    if (array_key_exists($tableDataKey, $this->idNameMapCols) &&
                        array_key_exists($d, $this->idNameMap)) {
                        $d = $this->idNameMap[$d];
                        if ($this->idNameMapRegex) {
                            $pattern = "/" . $this->idNameMapRegex . ".*$/";
                            $d = preg_replace($pattern, "", $d);
                        }
                    }
                    array_push($pdfDataRow, $d);
                    //if ((strlen($d) * $this->mult) > $pdfColWidths[$i]) {
                    //$pdfColWidths[$i] = (strlen($d) * $this->mult);
                    //}
                    $words = explode(" ", $d);
                    foreach ($words as $word) {
                        if ((strlen($word) * $this->mult) + $this->add > $pdfColWidths[$i]) {
                            $pdfColWidths[$i] = (strlen($word) * $this->mult) + $this->add;
                        }
                    }
                    $i++;
                }
                $html .= "</tr>";
                array_push($pdfData, $pdfDataRow); // Save this row.
                $pdfDataRow = array();             // Start a new row.
            }
            $pdf->AddPage();
            $pdf->setColWidths($pdfColWidths);
            $pdf->GenTable($pdfCaptionText, $pdfHeader, $pdfData);
            $html .= "</table></div>";
            
            if ($this->outputType == OutputTypes::Pdf) {
                $pdf->Output();
                exit();
            }
            if ($this->outputType == OutputTypes::Csv) {
                // Write the table title, headers, and data.
                $titleParts = explode("<br>", $pdfCaptionText);
                $csvTitle = $titleParts[0] . ": " . $this->typeOfReport . " " . $this->reportedItem;
                fputcsv($output, array($csvTitle));
                fputcsv($output, $pdfHeader);
                foreach ($pdfData as $pdfRow) {
                    fputcsv($output, $pdfRow);
                }
                fclose($output);
                exit();
            }
            
            echo $html;
        }
        
        private $idNameMapRegex = NULL;
        private $idNameMapCols = array();
        private $idNameMap = array();
        private $captionReplaceColKeys = array();
        private $captionReplaceColDefault = array();
        private $ignoreCols = array();
        private $idCol2EditUrl = array();
        private $valCol2IdCol = array();
        private $sql = NULL;
        private $db = NULL;
        private $caption = NULL;
        private $newTableColumns = array();
        private $headerTextMap = array();
        private $mult = 3.0;
        private $add = 2;
        private $typeOfReport = NULL;
        private $reportedItem = NULL;
        private $outputType = OutputTypes::Html;
        private $pdfFontSize = 12;
    }
    
    function addWhereClause(&$sql, &$db, $idHash) {
        $haveWhere = FALSE;
        if (count($idHash) > 0) {
            $sql .= " WHERE bl.block_id IN (";
            foreach ($idHash as $activeBlockId => $val) {
                if ($haveWhere) {
                    $sql .= ",?";
                } else {
                    $sql .= "?";
                }
                $haveWhere = TRUE;
                $db->addColVal($activeBlockId, 'i');
            }
            $sql .= ") ";
        }
        
        return $haveWhere;
    }
    
    abstract class ReportTypes
    {
        const None = 0;
        const ByEdah = 1;
        const ByChug = 2;
        const ByBunk = 3;
        const Director = 4;
        const CamperChoices = 5;
    }

    $dbErr = "";
    $sessionId2Name = array();
    $blockId2Name = array();
    $groupId2Name = array();
    $edahId2Name = array();
    $chugId2Name = array();
    $bunkId2Name = array();
    $reportMethodId2Name = array(
                                 ReportTypes::ByEdah        => "Yoetzet/Rosh Edah (by edah)",
                                 ReportTypes::ByBunk        => "Madrich (by bunk)",
                                 ReportTypes::ByChug        => "Chug Leader (by chug)",
                                 ReportTypes::Director      => "Director (whole camp, sorted by edah)",
                                 ReportTypes::CamperChoices => "Camper Prefs and Assignment"
                                );
    
    fillId2Name($chugId2Name, $dbErr,
                "chug_id", "chugim", "group_id",
                "groups");
    fillId2Name($sessionId2Name, $dbErr,
                "session_id", "sessions");
    fillId2Name($blockId2Name, $dbErr,
                "block_id", "blocks");
    fillId2Name($groupId2Name, $dbErr,
                "group_id", "groups");
    fillId2Name($edahId2Name, $dbErr,
                "edah_id", "edot");
    fillId2Name($bunkId2Name, $dbErr,
                "bunk_id", "bunks");

    $errors = array();
    $reportMethod = ReportTypes::None;
    $outputType = OutputTypes::Html;
    if ($_SERVER["REQUEST_METHOD"] == "GET") {
        $reset = test_input($_GET["reset"]);
        $reportMethod = test_input($_GET["report_method"]);
        $edahId = test_input($_GET["edah_id"]);
        $bunkId = test_input($_GET["bunk_id"]);
        $chugId = test_input($_GET["chug_id"]);
        $groupId = test_input($_GET["group_id"]);
        $blockId = test_input($_GET["block_id"]);
        $doReport = test_input($_GET["do_report"]);
        if (test_input($_GET["print"])) {
            $outputType = OutputTypes::Pdf;
        } else if (test_input($_GET["export"])) {
            $outputType = OutputTypes::Csv;
        }
        // Grab active block IDs.
        $activeBlockIds = array();
        populateActiveIds($activeBlockIds, "block_ids");

        // Report method is required for GET.  All other filter parameters are
        // optional (if we don't have a filter, we show everything).
        // Exception: if $reset is true, we set report type to none, and reset
        // other values.
        if ($reset) {
            $reportMethod = ReportTypes::None;
            $activeBlockIds = array();
            $edahId = NULL;
            $bunkId = NULL;
            $chugId = NULL;
            $groupId = NULL;
            $blockId = NULL;
        } else if ($reportMethod == NULL) {
            array_push($errors, errorString("Please choose a report type"));
        }
    } else {
        // Check for a query string like ?edah=1&block=1.  Use that to deduce
        // the report method and parameters.
        $parts = explode("&", $_SERVER['QUERY_STRING']);
        foreach ($parts as $part) {
            $cparts = explode("=", $part);
            if (count($cparts) != 2) {
                continue;
            }
            $lhs = strtolower($cparts[0]);
            
            // By-edah report.
            if ($lhs == "edah") {
                $reportMethod = ReportTypes::ByEdah;
                $edahId = $cparts[1];
                $doReport = 1;
            } else if ($lhs == "block") {
                $activeBlockIds[$cparts[1]] = 1;
            } else if ($lhs == "print") {
                $outputType = OutputTypes::Pdf;
            } else if ($lhs == "export") {
                $outputType = OutputTypes::Csv;
            }
            // Add more of these as needed.
            
        }
    }
    
    if ($outputType == OutputTypes::Html) {
        echo headerText("Chug Report");
    }
    
    $errText = genFatalErrorReport(array($dbErr));
    if (! is_null($errText)) {
        if ($outputType == OutputTypes::Html) {
            echo headerText("Chug Report");
        }
        echo $errText;
        exit();
    }
    
    // Per-chug report requires a chug ID to display.
    if ($reportMethod == ReportTypes::ByChug &&
        $doReport &&
        $chugId == NULL) {
        array_push($errors, errorString("Please choose a chug for this report"));
    }
    
    // Display errors and exit, if needed.
    $errText = genFatalErrorReport($errors);
    if (! is_null($errText)) {
        echo $errText;
        exit(); 
    }
    
    $actionTarget = htmlspecialchars($_SERVER["PHP_SELF"]);
    $pageStart = <<<EOM
<div class="report_form_container">
    
<h1><a>Chug Assignment Report</a></h1>
<form id="main_form" class="appnitro" method="GET" action="$actionTarget">
<div class="form_description">
<h2>Chug Assignment Report</h2>
<p>Start by choosing a report type, then select filters as needed.  Required options are marked with a <font color="red">*</font>.</p>
</div>
<ul>
    
EOM;
    if ($outputType == OutputTypes::Html) {
        echo $pageStart;
    }
    
    // Always show the report method drop-down.
    $reportMethodDropDown = new FormItemDropDown("Report Type", TRUE, "report_method", 0);
    $reportMethodDropDown->setGuideText("Step 1: Choose your report type.  Yoetzet/Rosh Edah report is by edah, Madrich by bunk, Chug leader by chug, and Director shows assignments for the whole camp.  Camper Prefs shows camper preferences and assignment, if any.");
    $reportMethodDropDown->setPlaceHolder("Choose Type");
    $reportMethodDropDown->setId2Name($reportMethodId2Name);
    $reportMethodDropDown->setColVal($reportMethod);
    $reportMethodDropDown->setInputSingular("Report Type");
    if ($reportMethod) {
        $reportMethodDropDown->setInputValue($reportMethod);
    }
    
    if ($outputType == OutputTypes::Html) {
        echo $reportMethodDropDown->renderHtml();
    }
    
    // All report methods include a time block filter.
    $liNumCounter = 0;
    if ($reportMethod) {
        $blockChooser = new FormItemInstanceChooser("Time Blocks", FALSE, "block_ids", $liNumCounter++);
        $blockChooser->setId2Name($blockId2Name);
        $blockChooser->setActiveIdHash($activeBlockIds);
        $blockChooser->setGuideText("Step 2: Choose the time block(s) you wish to display.  If you do not choose any, all blocks will be shown.");
        if ($outputType == OutputTypes::Html) {
            echo $blockChooser->renderHtml();
            // Add a hidden field indicating that the page should display a report
            // when this submit comes in.
            echo "<input type=\"hidden\" name=\"do_report\" id=\"do_report\" value=\"1\" />";
        }
    }
    
    // If we have a report method specified, display the appropriate filter fields.
    if ($reportMethod == ReportTypes::ByEdah) {
        // Display an optional Edah drop-down filter.
        $edahChooser = new FormItemDropDown("Edah", FALSE, "edah_id", $liNumCounter++);
        $edahChooser->setGuideText("Step 3: Choose an edah, or leave empty to see all edot");
        $edahChooser->setInputClass("element select medium");
        $edahChooser->setInputSingular("edah");
        $edahChooser->setColVal($edahId);
        $edahChooser->setId2Name($edahId2Name);
        
        if ($outputType == OutputTypes::Html) {
            echo $edahChooser->renderHtml();
        }
    } else if ($reportMethod == ReportTypes::ByBunk) {
        // Same as edah, but with a bunk filter.
        $bunkChooser = new FormItemDropDown("Bunk", FALSE, "bunk_id", $liNumCounter++);
        $bunkChooser->setGuideText("Step 3: Choose a bunk/tzrif, or leave empty to see all bunks");
        $bunkChooser->setInputClass("element select medium");
        $bunkChooser->setInputSingular("bunk");
        $bunkChooser->setColVal($bunkId);
        $bunkChooser->setId2Name($bunkId2Name);
        
        if ($outputType == OutputTypes::Html) {
            echo $bunkChooser->renderHtml();
        }
    } else if ($reportMethod == ReportTypes::ByChug) {
        // Similar to the above, but the filter is by chug.  Also, in this case, the
        // input is required.
        $chugChooser = new FormItemDropDown("Chug", TRUE, "chug_id", $liNumCounter++);
        $chugChooser->setGuideText("Step 3: Choose a chug for this report.");
        $chugChooser->setInputClass("element select medium");
        $chugChooser->setInputSingular("chug");
        $chugChooser->setColVal($chugId);
        $chugChooser->setId2Name($chugId2Name);
        
        if ($outputType == OutputTypes::Html) {
            echo $chugChooser->renderHtml();
        }
    } else if ($reportMethod == ReportTypes::CamperChoices) {
        // The camper choices report can be filtered by edah, group, and
        // block, e.g., Bogrim, Chug Aleph, weeks 1+2.
        $edahChooser = new FormItemDropDown("Edah", FALSE, "edah_id", $liNumCounter++);
        $edahChooser->setGuideText("Step 3: Choose an edah.  Leave empty to see campers in all edot.");
        $edahChooser->setInputClass("element select medium");
        $edahChooser->setInputSingular("edah");
        $edahChooser->setColVal($edahId);
        $edahChooser->setId2Name($edahId2Name);
        
        $groupChooser = new FormItemDropDown("Group", FALSE, "group_id", $liNumCounter++);
        $groupChooser->setGuideText("Choose a chug group, or leave empty to see all groups.");
        $groupChooser->setInputClass("element select medium");
        $groupChooser->setInputSingular("group");
        $groupChooser->setColVal($groupId);
        $groupChooser->setId2Name($groupId2Name);
        
        if ($outputType == OutputTypes::Html) {
            echo $edahChooser->renderHtml();
            echo $groupChooser->renderHtml();
        }
    } else if ($reportMethod == ReportTypes::Director) {
        // The director report shows all options, so there are no filter fields
        // except time block.
        
    }
    
    $cancelUrl = "";
    if (isset($_SESSION['admin_logged_in'])) {
        $cancelUrl = urlIfy("staffHome.php");
    } else {
        $cancelUrl = urlIfy("index.php");
    }
    
    $buttonText = "Go";
    if ($reportMethod) {
        $buttonText = "Display";
    }
    
    if ($outputType == OutputTypes::Html) {
        echo "<li class=\"buttons\">";
        echo "<input class=\"button_text\" type=\"submit\" name=\"submit\" value=\"$buttonText\" />";
        
        echo "<a href=\"$cancelUrl\">Home</a>";
        if ($doReport) {
            echo "<br><br><input class=\"control_button\" type=\"submit\" name=\"print\" title=\"Print this table\" value=\"Print...\" />";
            echo "<input class=\"control_button\" type=\"submit\" name=\"export\" title=\"Export to a file\" value=\"Export\" />";
        }
        echo "</li></ul></form>";
        
        echo "<form id=\"reset_form\" class=\"appnitro\" method=\"GET\" action=\"$actionTarget\">";
        echo "<ul><li class=\"buttons\">";
        echo "<input id=\"resetFormButton\" class=\"button_text\" type=\"submit\" name=\"reset\" value=\"Reset\" />";
        echo "</li></ul></form>";
    }
    
    if ($doReport) {
        // Prepare and display the report, setting the SQL according to the report
        // type.
        $db = new DbConn();
        $db->isSelect = TRUE;
        if ($reportMethod == ReportTypes::ByEdah) {
            // Per-edah report.
            $sql = "SELECT CONCAT(c.last, ', ', c.first) AS name, IFNULL(b.name,\"Not Selected\") bunk, bl.name block, e.name edah, e.sort_order edah_sort_order, " .
            "e.rosh_name rosh, e.rosh_phone roshphone, " .
            "g.name group_name, ch.name assignment, c.camper_id camper_id, b.bunk_id bunk_id, e.edah_id edah_id, g.group_id group_id, " .
            "ch.chug_id chug_id, bl.block_id block_id " .
            "FROM matches AS m " .
            "JOIN chug_instances AS i ON m.chug_instance_id = i.chug_instance_id " .
            "JOIN blocks AS bl ON i.block_id = bl.block_id " .
            "JOIN chugim AS ch ON i.chug_id = ch.chug_id " .
            "JOIN campers AS c ON c.camper_id = m.camper_id " .
            "JOIN edot AS e ON c.edah_id = e.edah_id " .
            "JOIN groups AS g ON g.group_id = ch.group_id " .
            "LEFT OUTER JOIN bunks b ON c.bunk_id = b.bunk_id ";
            $haveWhere = addWhereClause($sql, $db, $activeBlockIds);
            if ($edahId) {
                if (! $haveWhere) {
                    $sql .= "WHERE c.edah_id = ? ";
                } else {
                    $sql .= "AND c.edah_id = ? ";
                }
                $db->addColVal($edahId, 'i');
            }
            $sql .= "ORDER BY edah_sort_order, edah, name, block, group_name";
            
            // Create and display the report.
            $edahReport = new ZebraReport($db, $sql, $outputType);
            $edahReport->setReportTypeAndNameOfItem("Edah", $edahId2Name[$edahId]);
            $edahReport->addNewTableColumn("edah");
            $edahReport->setCaption("Chug Assignments by Edah for LINK0<br>Rosh: ROSH, PHONE");
            $edahReport->addIgnoreColumn("edah_sort_order");
            $edahReport->addIgnoreColumn("rosh");
            $edahReport->addIgnoreColumn("roshphone");
            $edahReport->setIdCol2EditPage("camper_id", "editCamper.php", "name");
            $edahReport->setIdCol2EditPage("bunk_id", "editBunk.php", "bunk");
            $edahReport->setIdCol2EditPage("edah_id", "editEdah.php", "edah");
            $edahReport->setIdCol2EditPage("group_id", "editGroup.php", "group_name");
            $edahReport->setIdCol2EditPage("chug_id", "editChug.php", "assignment");
            $edahReport->setIdCol2EditPage("block_id", "editBlock.php", "block");
            $edahReport->addCaptionReplaceColKey("ROSH", "rosh", "none listed");
            $edahReport->addCaptionReplaceColKey("PHONE", "roshphone", "no rosh phone");
            $edahReport->renderTable();
        } else if ($reportMethod == ReportTypes::ByBunk) {
            // Per-bunk report.  This the same as the per-edah report, except
            // organized by bunk.
            $sql = "SELECT CONCAT(c.last, ', ', c.first) AS name, IFNULL(b.name,\"Not Selected\") bunk, bl.name block, e.name edah, e.sort_order edah_sort_order, " .
            "g.name group_name, ch.name assignment, c.camper_id camper_id, b.bunk_id bunk_id, e.edah_id edah_id, g.group_id group_id, " .
            "ch.chug_id chug_id, bl.block_id block_id " .
            "FROM matches AS m " .
            "JOIN chug_instances AS i ON m.chug_instance_id = i.chug_instance_id " .
            "JOIN blocks AS bl ON i.block_id = bl.block_id " .
            "JOIN chugim AS ch ON i.chug_id = ch.chug_id " .
            "JOIN campers AS c ON c.camper_id = m.camper_id " .
            "JOIN edot AS e ON c.edah_id = e.edah_id " .
            "JOIN groups AS g ON g.group_id = ch.group_id " .
            "LEFT OUTER JOIN bunks b ON c.bunk_id = b.bunk_id ";
            $haveWhere = addWhereClause($sql, $db, $activeBlockIds);
            if ($bunkId) {
                if (! $haveWhere) {
                    $sql .= "WHERE b.bunk_id = ?";
                } else {
                    $sql .= "AND b.bunk_id = ? ";
                }
                $db->addColVal($bunkId, 'i');
            }
            $sql .= "ORDER BY bunk, name, edah_sort_order, edah, group_name";
            
            // Create and display the report.
            $bunkReport = new ZebraReport($db, $sql, $outputType);
            $bunkReport->setReportTypeAndNameOfItem("Bunk", $bunkId2Name[$bunkId]);
            $bunkReport->addNewTableColumn("bunk");
            $bunkReport->setCaption("Chug Assignments by Bunk for LINK0");
            $bunkReport->addIgnoreColumn("edah_sort_order");
            $bunkReport->setIdCol2EditPage("camper_id", "editCamper.php", "name");
            $bunkReport->setIdCol2EditPage("bunk_id", "editBunk.php", "bunk");
            $bunkReport->setIdCol2EditPage("edah_id", "editEdah.php", "edah");
            $bunkReport->setIdCol2EditPage("group_id", "editGroup.php", "group_name");
            $bunkReport->setIdCol2EditPage("chug_id", "editChug.php", "assignment");
            $bunkReport->setIdCol2EditPage("block_id", "editBlock.php", "block");
            $bunkReport->renderTable();
        } else if ($reportMethod == ReportTypes::ByChug) {
            // The chug report is meant for chug leaders.  The leaders need a separate sheet
            // for each edah that comes to the chug.  For each edah, the sheet should have:
            // - Rosh name and phone at the top, together with the edah name.
            // - List of campers in the edah: name and bunk.
            $sql = "SELECT CONCAT(c.last, ', ', c.first) AS camper, e.name edah, e.sort_order edah_sort_order, " .
            "e.rosh_name rosh, e.rosh_phone roshphone, ch.name chug_name, IFNULL(b.name, \"Not Selected\") bunk, bl.name block, " .
            "ch.chug_id chug_id, bl.block_id block_id, b.bunk_id bunk_id, e.edah_id edah_id, c.camper_id " .
            "FROM edot AS e " .
            "JOIN campers AS c ON c.edah_id = e.edah_id " .
            "JOIN matches AS m ON m.camper_id = c.camper_id " .
            "JOIN chug_instances AS i ON i.chug_instance_id = m.chug_instance_id " .
            "JOIN chugim AS ch ON ch.chug_id = i.chug_id " .
            "JOIN blocks AS bl ON bl.block_id = i.block_id " .
            "LEFT OUTER JOIN bunks AS b ON b.bunk_id = c.bunk_id ";
            $haveWhere = addWhereClause($sql, $db, $activeBlockIds);
            if ($haveWhere) {
                $sql .= " AND ch.chug_id = ? ";
            } else {
                $sql .= " WHERE ch.chug_id = ? ";
            }
            $db->addColVal($chugId, 'i');
            $sql .= " ORDER BY edah_sort_order, edah, block, camper, bunk";
            
            $chugReport = new ZebraReport($db, $sql, $outputType);
            $chugReport->setReportTypeAndNameOfItem("Chug", $chugId2Name[$chugId]);
            $chugReport->addNewTableColumn("edah");
            $chugReport->addIgnoreColumn("edah_sort_order");
            $chugReport->addIgnoreColumn("rosh");
            $chugReport->addIgnoreColumn("roshphone");
            $chugReport->addIgnoreColumn("chug_name");
            $chugReport->addIgnoreColumn("block");
            $chugReport->setIdCol2EditPage("camper_id", "editCamper.php", "camper");
            $chugReport->setIdCol2EditPage("chug_id", "editChug.php", "chug_name");
            $chugReport->setIdCol2EditPage("bunk_id", "editBunk.php", "bunk");
            $chugReport->setIdCol2EditPage("block_id", "editBlock.php", "block");
            $chugReport->setIdCol2EditPage("edah_id", "editEdah.php", "edah");
            $chugReport->setCaption("LINK0 campers for BLOCK<br>Rosh: ROSH, PHONE");
            $chugReport->addCaptionReplaceColKey("ROSH", "rosh", "none listed");
            $chugReport->addCaptionReplaceColKey("PHONE", "roshphone", "no rosh phone");
            $chugReport->addCaptionReplaceColKey("BLOCK", "block", "no block name");
            $chugReport->renderTable();
        } else if ($reportMethod == ReportTypes::CamperChoices) {
            // Report camper choices (1-6) and assignment, if any.
            $sql = "SELECT CONCAT(c.last, ', ', c.first) AS name, bl.name block, e.name edah, e.sort_order edah_sort_order, " .
            "bl.name block, g.name group_name, IFNULL(ma.chug_name, \"Not Assigned Yet\") assignment, e.edah_id edah_id, g.group_id group_id, bl.block_id block_id, " .
            "p.first_choice_id first_choice, p.second_choice_id second_choice, p.third_choice_id third_choice, " .
            "p.fourth_choice_id fourth_choice, p.fifth_choice_id fifth_choice, p.sixth_choice_id sixth_choice " .
            "FROM campers AS c " .
            "JOIN preferences AS p ON c.camper_id = p.camper_id " .
            "JOIN groups AS g ON g.group_id = p.group_id " .
            "JOIN blocks AS bl ON bl.block_id = p.block_id " .
            "JOIN edot AS e ON c.edah_id = e.edah_id " .
            "LEFT OUTER JOIN  " .
            "(SELECT ma.camper_id camper_id, i.block_id block_id, ch.group_id group_id, ch.name chug_name " .
            "FROM matches AS ma, chug_instances AS i, chugim AS ch " .
            "WHERE ma.chug_instance_id = i.chug_instance_id " .
            "AND i.chug_id = ch.chug_id) AS ma " .
            "ON ma.camper_id = c.camper_id AND ma.block_id = bl.block_id AND ma.group_id = g.group_id ";
            $haveWhere = addWhereClause($sql, $db, $activeBlockIds);
            if ($edahId) {
                if (! $haveWhere) {
                    $sql .= "WHERE c.edah_id = ? ";
                    $haveWhere = TRUE;
                } else {
                    $sql .= "AND c.edah_id = ? ";
                }
                $db->addColVal($edahId, 'i');
            }
            if ($groupId) {
                if (! $haveWhere) {
                    $sql .= "WHERE g.group_id = ? ";
                    $haveWhere = TRUE;
                } else {
                    $sql .= "AND g.group_id = ? ";
                }
                $db->addColVal($groupId, 'i');
            }
            $sql .= "ORDER BY edah_sort_order, edah, block, name, group_name";
            $camperReport = new ZebraReport($db, $sql, $outputType);
            $camperReport->setIdNameMap($chugId2Name, " -");
            $camperReport->addIdNameMapCol("first_choice");
            $camperReport->addIdNameMapCol("second_choice");
            $camperReport->addIdNameMapCol("third_choice");
            $camperReport->addIdNameMapCol("fourth_choice");
            $camperReport->addIdNameMapCol("fifth_choice");
            $camperReport->addIdNameMapCol("sixth_choice");
            $camperReport->addNewTableColumn("edah");  // New table when edah changes
            $camperReport->addNewTableColumn("block"); // New table when block changes
            $camperReport->setCaption("LINK0 Camper Preferences and Assignments for LINK1");
            $camperReport->addIgnoreColumn("edah_sort_order");
            $camperReport->addIgnoreColumn("block");
            $camperReport->setIdCol2EditPage("block_id", "editBlock.php", "block");
            $camperReport->setIdCol2EditPage("edah_id", "editEdah.php", "edah");
            $camperReport->setIdCol2EditPage("group_id", "editGroup.php", "group_name");
            $camperReport->addCaptionReplaceColKey("BLOCK", "block", "no block name");
            $camperReport->setPdfFontSizeAndMult(9.0,2.0);
            $camperReport->renderTable();
        } else if ($reportMethod == ReportTypes::Director) {
            // The director report is similar to the edah report, but unfiltered.
            $sql = "SELECT CONCAT(c.last, ', ', c.first) AS name, IFNULL(b.name, \"Not Selected\") bunk, bl.name block, e.name edah, e.sort_order edah_sort_order, " .
            "g.name group_name, ch.name assignment, c.camper_id camper_id, b.bunk_id bunk_id, e.edah_id edah_id, g.group_id group_id, " .
            "ch.chug_id chug_id, bl.block_id block_id " .
            "FROM matches AS m " .
            "JOIN chug_instances AS i ON i.chug_instance_id = m.chug_instance_id " .
            "JOIN blocks AS bl ON bl.block_id = i.block_id " .
            "JOIN chugim AS ch ON ch.chug_id = i.chug_id " .
            "JOIN campers AS c ON c.camper_id = m.camper_id " .
            "JOIN edot AS e ON e.edah_id = c.edah_id " .
            "JOIN groups AS g ON g.group_id = ch.group_id " .
            "LEFT OUTER JOIN bunks AS b ON b.bunk_id = c.bunk_id ";
            addWhereClause($sql, $db, $activeBlockIds);
            $sql .= " ORDER BY edah_sort_order, edah, name, block, group_name";
            
            // Create and display the report.
            $directorReport = new ZebraReport($db, $sql, $outputType);
            $directorReport->addNewTableColumn("edah");
            $directorReport->setCaption("Chug Assignments by Edah for LINK0");
            $directorReport->addIgnoreColumn("edah_sort_order");
            $directorReport->setIdCol2EditPage("camper_id", "editCamper.php", "name");
            $directorReport->setIdCol2EditPage("bunk_id", "editBunk.php", "bunk");
            $directorReport->setIdCol2EditPage("edah_id", "editEdah.php", "edah");
            $directorReport->setIdCol2EditPage("group_id", "editGroup.php", "group_name");
            $directorReport->setIdCol2EditPage("chug_id", "editChug.php", "assignment");
            $directorReport->setIdCol2EditPage("block_id", "editBlock.php", "block");
            $directorReport->renderTable();
        }
    }
    
    if ($outputType == OutputTypes::Html) {
        echo "</div>";
        echo footerText();
        echo "<img id=\"bottom\" src=\"images/bottom.png\" alt=\"\">";
        echo "</body></html>";
    }
    
    ?>


    
    
    
    
    
    
    
    
    
    
