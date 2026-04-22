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
$html = file_get_contents("jobTag.html");
/*-----------------------------------------------------------------*/
$html = str_replace("{{projectManager}}", "Jun Liu", $html);
/*-----------------------------------------------------------------*/
$domPdf->loadHtml($html);
$domPdf->setPaper("A4", "portrait");
$domPdf->render();
$output = $domPdf->output();
file_put_contents("jobTag.pdf", $output);