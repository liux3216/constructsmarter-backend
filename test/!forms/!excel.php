<?php
exit();
// hello world d
//required headers:
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Content-Type: multipart/form-data; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
// load dependencies:
require_once "components/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as PdfWriter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as excelReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as excelWriter;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\IOFactory;

$excelReader = new excelReader();
$spreadsheet = $excelReader->load("po.xlsx");
$writer = new Html($spreadsheet);
$htmlContent = $writer->save("output.html");
