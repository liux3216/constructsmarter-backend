<?php
exit();
//required headers:
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Content-Type: multipart/form-data; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
// load dependencies:
require_once "components/vendor/autoload.php";
use Dompdf\Dompdf;
/*-----------------------------------------------------------------*/
$domPdf = new Dompdf();
$domPdf->set_option("isHtml5ParserEnabled", true);
// $domPdf->set_option("isRemoteEnabled", true);
// error_log($domPdf->getOptions()->getChroot()[0]);
// default: "/opt/bitnami/apache/htdocs/components/vendor/dompdf/dompdf"
$domPdf->getOptions()->setChroot("/opt/bitnami/apache/htdocs"); // for img src: needs to change in every run
$html = file_get_contents("purchaseOrderForm.html");
/*-----------------------------------------------------------------*/
$html = str_replace("{{poNumber}}", "1", $html);
$html = str_replace("{{date}}", "2", $html);
$html = str_replace("{{poCategory}}", "3", $html);
$html = str_replace("{{requestor}}", "4", $html);
$html = str_replace("{{projectName}}", "5", $html);
$html = str_replace("{{department}}", "6", $html);
$html = str_replace("{{truckNumber}}", "7", $html);
$html = str_replace("{{paymentMethod}}", "8", $html);
$html = str_replace("{{billable}}", "9", $html);
$html = str_replace("{{vendorName}}", "10", $html);
$html = str_replace("{{description}}", "11", $html);
$html = str_replace("{{qty}}", "12", $html);
$html = str_replace("{{unitPrice}}", "13", $html);
$html = str_replace("{{lineTotal}}", "14", $html);
$html = str_replace("{{subtotal}}", "15", $html);
$html = str_replace("{{tax}}", "16", $html);
$html = str_replace("{{discount}}", "17", $html);
$html = str_replace("{{total}}", "18", $html);
$html = str_replace("{{comments}}", "19", $html);
/*-----------------------------------------------------------------*/
$html = str_replace("<!-- approver -->", "20", $html);
$html = str_replace("<!-- approverDate -->", "21", $html);
/*-----------------------------------------------------------------*/
$dom = new DOMDocument();
$dom->loadHTML($html);
if($decision){
    $element = $dom->getElementById("decisionStampTable");
    $element->setAttribute("style", "");
}
$html = $dom->saveHTML();
/*-----------------------------------------------------------------*/
$domPdf->loadHtml($html);
$domPdf->setPaper("A4", "portrait");
$domPdf->render();
$output = $domPdf->output();
file_put_contents("purchaseOrderForm.pdf", $output);