<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       cfdixml/cfdixmlindex.php
 *	\ingroup    cfdixml
 *	\brief      Home page of cfdixml top menu
 */
// print_r($_POST);exit;
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
include_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
include_once DOL_DOCUMENT_ROOT . '/core/lib/payments.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
include_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
include_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/cfdixml/lib/cfdixml.lib.php');
// dol_include_once('/cfdixml/lib/form.lib.php');
dol_include_once('/cfdixml/class/cfdiutils.class.php');
dol_include_once('/cfdixml/class/facturalo.class.php');


// Load translation files required by the page
$langs->loadLangs(array("cfdixml@cfdixml"));
$action = GETPOST('action', 'aZ09');
$id = GETPOST('id', 'int');

$hookmanager->initHooks(array('cfdixmlpaymentcard', 'globalcard'));

$invoice = new Facture($db);
$payment = new Paiement($db);
$societe = new Societe($db);
$extrafields = new ExtraFields($db);
$formfile = new FormFile($db);
$payment->fetch($id);
$payment->fetch_optionals();


// Validate if Inivoice have PUE and reject
$sql = 'SELECT f.rowid as facid';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'paiement_facture as pf,' . MAIN_DB_PREFIX . 'facture as f,' . MAIN_DB_PREFIX . 'societe as s';
$sql .= ' WHERE pf.fk_facture = f.rowid';
$sql .= ' AND f.fk_soc = s.rowid';
$sql .= ' AND f.entity IN (' . getEntity('invoice') . ')';
$sql .= ' AND pf.fk_paiement = ' . ((int) $payment->id);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            $invoice->fetch($obj->facid);
            $extrafields->fetch_name_optionals_label($invoice->table_element);
            $societe->fetch($invoice->socid);
            // echo '<pre>';print_r($invoice);exit;
            if ($invoice->array_options['options_cfdixml_metodopago'] == 'PUE') {
                setEventMessage('No se puede timbrar un pago de una factura PUE o la/s factura/s no está/n timbrada/s', 'errors');
                header('Location:' . DOL_MAIN_URL_ROOT . '/compta/paiement/card.php?id=' . $payment->id);
                exit;
            }
            $i++;
        }
    }
}

//Action

if ($action == 'stamp') {


    if ($payment->array_options['options_cfdixml_UUID']) {
        setEventMessage('El pago ya está timbrado', 'errors');
        header('Location:' . $_SERVER['PHP_SELF'] . '?id=' . $payment->id);
        exit;
    }


    if (empty($payment->array_options['options_cfdixml_control'])) {

        $comprobanteAtributos["Fecha"] = date("Y-m-d") . "T" . date("H:i:s");
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "paiement_extrafields ";
        $sql .= " (fk_object,cfdixml_control) VALUES (" . $id . ",'" . date('Y-m-d') . 'T' . date('H:i:s') . "')";
        $result = $db->query($sql);
    } else {
        $comprobanteAtributos["Fecha"] = $payment->array_options['options_cfdixml_control'];
    }

    $comprobanteAtributos["Moneda"]               = "XXX";
    $comprobanteAtributos["SubTotal"]             = 0;
    $comprobanteAtributos["Total"]                = 0;
    $comprobanteAtributos["TipoDeComprobante"]    = "P";
    $comprobanteAtributos["LugarExpedicion"]      = $conf->global->MAIN_INFO_SOCIETE_ZIP;
    $comprobanteAtributos["Version"]              = "4.0";
    $comprobanteAtributos["Exportacion"]          = "01";

    $receptor = getReceptor($invoice, $societe);
    $receptor['UsoCFDI'] = 'CP01'; //TODO: Make dynamic

    $cfdiutils = new CfdiUtils();
    // echo '<pre>';print_r(getPayments($payment));exit;
    $xml = $cfdiutils->preCfdi(
        $comprobanteAtributos,
        getEmisor(),
        $receptor,
        'concepto',
        getPayments($payment),
        $conf->global->CFDIXML_CER_FILE,
        $conf->global->CFDIXML_KEY_FILE,
        $conf->global->CFDIXML_CERKEY_PASS
    );
    // echo '<pre>';print_r($xml);exit;

    if (!file_exists($conf->cfdixml->multidir_output[$invoice->entity] . '/payment/' . dol_sanitizeFileName($payment->ref) . "/" . $payment->ref . ".xml")) {
        mkdir($conf->cfdixml->multidir_output[$invoice->entity] . '/payment/' . dol_sanitizeFileName($payment->ref), 0755, true);
    }
    $filedir = $conf->cfdixml->multidir_output[$invoice->entity] . '/payment/' . dol_sanitizeFileName($payment->ref);

    $file_xml = fopen($filedir . "/" . $payment->ref . ".xml", "w");
    fwrite($file_xml, utf8_encode($xml));
    fclose($file_xml);


    /* Finkok */
    $cfdi = $cfdiutils->quickStamp($xml, $conf->global->CFDIXML_WS_TOKEN, $conf->global->CFDIXML_WS_MODE);

    //FINKOK
    if ($cfdi['code'] == '400') setEventMessage($cfdi['data'], 'errors');
    if ($cfdi['code'] == '400') header('Location:' . $_SERVER['PHP_SELF'] . '?facid=' . $object->id);
    if ($cfdi['code'] == '200' || $cfdi['code'] == '307') goto saveXML;
    if ($cfdi['code'] != '200') {

        setEventMessage($cfdi['code'] . ' - ' . $cfdi['message'], 'errors');
        header('Location:' . $_SERVER['PHP_SELF'] . '?id=' . $payment->id);
        $invoice->array_options['options_cfdixml_control'] = '';
        $invoice->update($user, 1);
    }
    exit;
    saveXML:
    $data = $cfdiutils->getData($cfdi['data']);


    /* Facturalo */

    // if ($conf->global->CFDIXML_WS_MODE == 'TEST') $url = $conf->global->CFDIXML_WS_TEST;
    // if ($conf->global->CFDIXML_WS_MODE == 'PRODUCTION') $url = $conf->global->CFDIXML_WS_PRODUCTION;

    // $conexion = new Conexion($url);
    // $cfdi = $conexion->operacion_timbrar3($conf->global->CFDIXML_WS_TOKEN, $xml);
    // if ($cfdi['code'] == '307') goto saveXML;
    // if ($cfdi['code'] != '200') {

    //     setEventMessage($cfdi['mensaje'], 'errors');
    //     header('Location:' . $_SERVER['PHP_SELF'] . '?id=' . $payment->id);
    //     exit;
    // }


    $sql = "UPDATE " . MAIN_DB_PREFIX . "paiement_extrafields SET ";
    $sql .= "cfdixml_UUID = '" . $data['UUID'] . "',";
    $sql .= "cfdixml_fechatimbrado = '" . $data['FechaTimbrado'] . "',";
    $sql .= "cfdixml_sellosat ='" . $data['SelloSAT'] . "',";
    $sql .= "cfdixml_certsat = '" . $data['CertSAT'] . "',";
    $sql .= "cfdixml_sellocfd ='" . $data['SelloCFD'] . "',";
    $sql .= "cfdixml_certcfd ='" . $data['CertCFD'] . "',";
    $sql .= "cfdixml_cadenaorig ='" . $data['CadenaOriginal'] . "',";
    $sql .= "cfdixml_xml ='" . base64_encode($cfdi['data']) . "',";
    $sql .= "cfdixml_control = ''";
    $sql .= " WHERE fk_object = " . $id;

    $result = $db->query($sql);

    $filedir = $conf->cfdixml->multidir_output[$invoice->entity] . '/payment/' . dol_sanitizeFileName($payment->ref);

    $file_xml = fopen($filedir . "/" . $payment->ref . '_' . $data['UUID'] . ".xml", "w");
    fwrite($file_xml, mb_convert_encoding($cfdi['data'], 'utf8'));
    fclose($file_xml);
    $invoice->generateDocument('cfdixml', $langs, false, false);

    setEventMessage('Factura timbrada con éxito UUID:' . $data['UUID'], 'mesgs');
    header('Location:' . $_SERVER['PHP_SELF'] . '?id=' . $payment->id);
    exit;
}
if ($action == 'confirm_cancel' && GETPOST('confirm') == 'yes') {
    //echo '<pre>';print_r($_GET);exit;

    //Finkok
    $cfdiutils = null;
    $cfdiutils = new CfdiUtils();
    try {
        $result = $cfdiutils->CancelDocument(
            $payment->array_options['options_cfdixml_UUID'],
            GETPOST('motivo'),
            $conf->global->CFDIXML_WS_MODE,
            $conf->global->CFDIXML_CER_FILE,
            $conf->global->CFDIXML_KEY_FILE,
            $conf->global->CFDIXML_CERKEY_PASS,
            $conf->global->CFDIXML_WS_TOKEN
        );


        //Finkok
        // echo '<pre>';print_r($result->voucher());echo '</pre>';
        // echo '<pre>';print_r($result->date());echo '</pre>';
        // echo '<pre>';print_r($result->statusCode());echo '</pre>';

        // exit;
        $xmlcanceled =  $result->voucher();

        $filedir = $conf->cfdixml->multidir_output[$invoice->entity] . '/payment/' . dol_sanitizeFileName($payment->ref);
        //Finkok
        $file_xml = fopen($filedir . "/ACUSE_CANCELACION_" . $payment->ref . '_' . $payment->array_options['options_cfdixml_UUID'] . ".xml", "w");
        fwrite($file_xml, mb_convert_encoding($xmlcanceled,'UTF8'));
        fclose($file_xml);

        // //Provisional FIX

        $sql = "UPDATE " . MAIN_DB_PREFIX . "paiement_extrafields ";
        $sql .= " SET cfdixml_fechacancelacion = '" . $result->date() . "'";
        $result->statusCode() ? $sql .= ", cfdixml_codigocancelacion = '" . $result->statusCode() . "'" : null;
        $sql .= ", cfdixml_xml_cancelacion = \"" . base64_encode($xmlcanceled) . "\"";
        $sql .= " WHERE fk_object = " . $payment->id;

        $result = $db->query($sql);
        $db->commit();


        // Not update correct invoice, update $object ¿why?
        // $invoicetocancel->array_options['options_cfdixml_fechacancelacion'] = $result->date();
        // $invoicetocancel->array_options['options_cfdixml_codigoncelacion'] = $result->statusCode();
        // $invoicetocancel->array_options['options_cfdixml_xml_cancel'] = $xmlcanceled;

        // $result = $invoicetocancel->update($user, 1);

    } catch (Exception $e) {

        dol_syslog("Exception Cancel Invoice: " . $e);
    }
}

if ($action == 'builddoc') {


    $invoice->generateDocument('cfdixml', $langs, false, false);
}

//View
$form = new Form($db);
llxHeader('', $langs->trans("REP"));

$head = payment_prepare_head($payment);

print dol_get_fiche_head($head, 'pagocfdi', $langs->trans('SupplierPayment'), -1, 'payment');

$linkback = '<a href="' . DOL_URL_ROOT . '/compta/paiement/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

dol_banner_tab($payment, 'ref', $linkback, 1, 'ref', 'ref', '');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

//Versión Pago
print '<tr>';
print '<td class="titlefield fieldname_type">Complemento de pago</td><td class="valuefield fieldname_type"><span class="badgeneutral"> Versión 2.0</span></td>'; //TODO make dynamic
print '</tr>';

//Versión Pago
print '<tr>';
print $payment->array_options['options_cfdixml_UUID'] ? '<td class="titlefield fieldname_type">Estado</td><td class="valuefield fieldname_type"><span class="badgeneutral badge-status2">Timbrado</span></td>' : '<td class="titlefield fieldname_type">Estado</td><td class="valuefield fieldname_type"><span class="badgeneutral"> Sin timbrar</span></td>'; //TODO make dynamic
print '</tr>';

//Fecha pago
print '<tr>';
print '<td>Fecha del Pago</td>';
print '<td>' . dol_print_date($payment->date, '%d/%m/%Y %H:%m') . '</td>';
print '</tr>';

//Uso del CFDI
print '<tr>';
print '<td>Uso del CFDI</td>';
print '<td>';
print $payment->array_options['options_cfdixml_usocfdi'] ? $payment->array_options['options_cfdixml_usocfdi'] : 'P01 - Por Definir';
print '</td>';
print '</tr>';

//Bank Account


$bankline = new AccountLine($db);

if ($payment->fk_account > 0) {
    $bankline->fetch($payment->bank_line);
    if ($bankline->rappro) {
        $disable_delete = 1;
        $title_button = dol_escape_htmltag($langs->transnoentitiesnoconv("CantRemoveConciliatedPayment"));
    }

    print '<tr>';
    print '<td>' . $langs->trans('BankAccount') . '</td>';
    print '<td>';
    $accountstatic = new Account($db);
    $accountstatic->fetch($bankline->fk_account);
    print $accountstatic->getNomUrl(1);
    print '</td>';
    print '</tr>';
}
//Accoutancy Register
print '<tr>';
print '<td>' . $langs->trans('BankTransactionLine') . '</td>';
print '<td>';
if ($payment->fk_account > 0) {
    // var_dump($bankline);
    print $bankline->getNomUrl(1, 0, 'showconciliatedandaccounted');
}
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

//Amount
print '<div class="fichehalfright">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

print '<tr>';
print '<td>Total Pago</td>';
print '<td>';
// echo '<pre>';print_r($payment);exit;
print price($payment->amount, '', $langs, 0, -1, -1, $conf->currency);
print '</td>';
print '</tr>';

//Paymnent Method
print '<tr>';
print '<td>Método de pago</td>';
print '<td>';
//    echo '<pre>';print_r($payment);exit;
print $payment->type_label;
print '</td>';
print '</tr>';

//Operation Number
print '<tr>';
print '<td>Número de Operación</td>';
print '<td>';
//  echo '<pre>';print_r($payment);exit;
print $payment->num_payment;
print '</td>';
print '</tr>';

//Currency
print '<tr>';
print '<td>Moneda</td>';
print '<td>';
echo $conf->currency;
print '</td>';
print '</tr>';

//Multicurrency Exchange
print '<tr>';
print '<td>Tipo de Cambio</td>';
print '<td>';
print 'N/D';
// echo $conf->currency;
print '</td>';
print '</tr>';
print '<tr class="liste_titre"><td colspan="2" class="titlefield">Complemento Electrónico de Pago</td></tr>';

//File CEP

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="addcep">';
print '<tr>';
print '<td>';
print '<input type="file" name="cep" id="cep"  class="flat minwidth500">';
print '</td>';
print '<td>';
print '<input type="submit" class="button button-save" name="add_cep" value="Cargar CEP">';
print '</td>';
print '</form>';
print '</table>';
print '</div>';
print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

if ($payment->array_options['options_cfdixml_UUID']) {
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr><td colspan="2">CFDI Recibo de Pago</td></tr>';
    print '<tr><td>UUID</td><td style="">' . $payment->array_options['options_cfdixml_UUID'] . '</td></tr>';
    print '<tr><td>Fecha de timbrado</td><td style="">' . $payment->array_options['options_cfdixml_fechatimbrado'] . '</td></tr>';
    print '<tr><td>Certificado SAT</td><td style="">' . $payment->array_options['options_cfdixml_certsat'] . '</td></tr>';
    // print '<tr><td>Sello SAT</td><td style="width:100px;word-break:break-all;">'.$payment->array_options['options_cfdixml_sellosat'].'</td></tr>';
    print '<tr><td>Certificado CFD</td><td style="">' . $payment->array_options['options_cfdixml_certcfd'] . '</td></tr>';
    // print '<tr><td>Sello CFD</td><td  style="width:100px;word-break:break-all;">'.$payment->array_options['options_cfdixml_sellocfd'].'</td></tr>';
    // print '<tr><td>Cadena Original</td><td style="">'.$payment->array_options['options_cfdixml_cadenaorig'].'</td></tr>';
    print '</table>';
    print '</div>';
}
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td style="text-align:center;" colspan="4" class="titlefield"><strong>Detalles Complemento Electrónico de Pago</strong></td></tr>';
print '<tr class="liste_titre"><td colspan="2" class="titlefield">Emisor</td><td colspan="2" class="titlefield">Receptor</td></tr>';
print '<tr>';
print '<td>RFC Banco</td>';
print '<td></td>';
print '<td>RFC Banco</td>';
print '<td></td>';
print '</tr>';
print '<tr>';
print '<td>Num Cta Banco</td>';
print '<td></td>';
print '<td>Num Cta Banco</td>';
print '<td></td>';
print '</tr>';
print '<tr>';
print '<td>Cadena de Pago</td>';
print '<td colspan="3"></td>';
print '</tr>';
print '<tr>';
print '<td>Certificado del pago</td>';
print '<td colspan="3"></td>';
print '</tr>';
print '<tr>';
print '<td>Cadena original del pago</td>';
print '<td colspan="3"></td>';
print '</tr>';
print '<tr>';
print '<td>Sello del pago</td>';
print '<td colspan="3"></td>';
print '</tr>';
print '</table>';
print '</div>';
print '<br><br>';
print '<div class="fichehalfcenter">';
print '<div class="underbanner clearboth"></div>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre nodrag nodrop">';
print '<td class="linecoldescription">Factura</td>';
print '<td class="linecoldescription">Total factura</td>';
print '<td class="linecoldescription">Saldo anterior</td>';
print '<td class="linecoldescription">Monto pago</td>';
print '<td class="linecoldescription">Parcialidad</td>';
print '<td class="linecoldescription">Saldo insoluto</td>';

print '</tr>';

//Print invoices
// Validate if Inivoice have PUE and reject
$sql = 'SELECT f.rowid as facid';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'paiement_facture as pf,' . MAIN_DB_PREFIX . 'facture as f,' . MAIN_DB_PREFIX . 'societe as s';
$sql .= ' WHERE pf.fk_facture = f.rowid';
$sql .= ' AND f.fk_soc = s.rowid';
$sql .= ' AND f.entity IN (' . getEntity('invoice') . ')';
$sql .= ' AND pf.fk_paiement = ' . ((int) $payment->id);
$resql = null;
$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        $i = 0;
        while ($i < $num) {
            print '<tr>';
            $obj = $db->fetch_object($resql);

            $invoice->fetch($obj->facid);

            //factura
            print '<td>' . $invoice->getNomUrl(1) . '</td>';
            // total factura
            print '<td>' . price($invoice->total_ttc, 2, '', 1, 2, -1, $invoice->multicurrency_code) . '</td>';


            $sql = "SELECT count(*) as nb from " . MAIN_DB_PREFIX . "paiement_facture where fk_facture = " . $obj->facid;
            $sql .= " AND fk_paiement <> " . $id;
            $resqlpay = $db->query($sql);

            if ($resqlpay) {
                $objtotalpay = $db->fetch_object($resqlpay);

                if ($objtotalpay->nb > 0) {

                    //TODO: verify if all payments before this have's UUID

                } else {
                    $sql = "SELECT amount from " . MAIN_DB_PREFIX . "paiement_facture where fk_facture = " . $obj->facid;
                    $sql .= " AND fk_paiement = " . $id;

                    $resqlp = $db->query($sql);
                    if ($resqlp) {
                        $objpay = $db->fetch_object($resqlp);

                        //Saldo Anterior
                        print '<td>' . price($invoice->total_ttc, 2, '', 1, 2, -1, $invoice->multicurrency_code) . '</td>';
                        //Total Pagado

                        $totalpay = $objpay->amount + ($objpay->amount - $invoice->total_ttc);
                        print '<td>' . price($totalpay, 2, '', 1, 2, -1, $invoice->multicurrency_code) . '<br>';

                        //Parcialidad

                        //Make dynamic
                        print '<td>';
                        if (empty($payment->array_options['options_cfdixml_UUID'])) {
                            print getPaymentNum($invoice);
                        } else {
                            $filename = dol_sanitizeFileName($payment->ref);
                            $filedir = $conf->cfdixml->multidir_output[$conf->entity] . '/payment/' . dol_sanitizeFileName($payment->ref);

                            if (file_exists($filedir . '/' . $filename . '_' . $payment->array_options['options_cfdixml_UUID'] . '.xml')) {
                                $data = file_get_contents($filedir . '/' . $filename . '_' . $payment->array_options['options_cfdixml_UUID'] . '.xml');
                                print getPaymentNum($invoice, $data);
                            }
                            //'_'. $data->UUID .".xml"
                        }
                        print '</td>';


                        //Saldo insoluto

                        $restopay = $invoice->total_ttc - $totalpay;
                        print '<td>' . price($restopay, 2, '', 1, 2, -1, $invoice->multicurrency_code) . '<br>';
                    }
                }
            }
            print '</tr>';
            $i++;
        }
    }
}
print '</div>';
print '</table>';
//Action Buttons

if($action == 'cancel'){

    $cancelacion = getDictionaryValues('cancelacion');
    $formquestion = [
        'text' => '<h2>Cancelar fiscalmente el pago ' . $payment->ref . '</h2>',
        ['type' => 'select', 'name' => 'motivo', 'id' => 'motivo', 'label' => 'Motivo', 'values' => $cancelacion]

    ];

    $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $payment->id, $langs->trans('cancel'), '', 'confirm_cancel', $formquestion, 0, 1, 310, 500);

    print $formconfirm;

    echo '<script>$(document).ready(function(){
        $(".select2-container").css("width","20rem");
        });</script>';

}

if ($action == 'presend') {


    $modelmail = '';
    $defaulttopic = 'Enviar correo';
    //$diroutput = $conf->cfdixml->multidir_output[$invoice->entity] . '/payment/' . dol_sanitizeFileName($payment->ref).'/';
    //$diroutput = $conf->cfdixml->dir_output."/test/";
    //$trackid = 'email_payment'.$id;
    //$trackid = $payment->id;
    $object = $payment;
    print '<div class="centpercent notopnoleftnoright table-fiche-title">';

    dol_include_once('/cfdixml/tpl/other_card_presend.tpl.php');
    print '</div>';
} else  {
    print '<div class="tabsAction">';
    if(!empty($payment->array_options['options_cfdixml_UUID'])){

        if(empty($payment->array_options['options_cfdixml_fechacancelacion'])) print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=cancel" class="butActionDelete">Cancelar Pago</a>';
    }
    // print $payment->array_options['options_cfdixml_UUID'] ?  : '';

    print $payment->array_options['options_cfdixml_UUID'] ? '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=builddoc" class="butAction">Regenerar PDF</a>' : '';
    print $payment->array_options['options_cfdixml_UUID'] ? '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=presend" class="butAction">Enviar email</a>' : '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=stamp" class="butAction">Timbrar CFDI</a>';
    print '</div>';

    print '<div class="fichecenter"><div class="fichehalfleft">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">Documentos</td></tr>';

	// Generated documents
	// echo'<pre>';print_r($payment);exit;
	//Solución temporal
	$filename = 'payment/' . dol_sanitizeFileName($payment->ref);
	$filedir = $conf->cfdixml->multidir_output[$conf->entity] . '/payment/' . dol_sanitizeFileName($payment->ref);
	// $sql =" UPDATE ".MAIN_DB_PREFIX."ecm_files SET share = '". base64_encode($payment->ref.".xml"). "' WHERE filename = '". $payment->ref."_".$payment->array_options['options_cfdixml_UUID'].".xml';";
	// echo $sql;
	// $resql = $db->query($sql);
	// $sql = " UPDATE " . MAIN_DB_PREFIX . "ecm_files SET share = '" . base64_encode($payment->ref . ".pdf") . "' WHERE filename = '" . $payment->ref . "_" . $payment->array_options['options_cfdixml_UUID'] . ".pdf';";
	// 	$urlsource = $_SERVER['PHP_SELF'].'?id='.$payment->id;
	// $resql = $db->query($sql);

	print $formfile->showdocuments(
		'cfdixml',
		$filename,
		$filedir,
		$urlsource,
		$genallowed,
		$delallowed,
		$payment->model_pdf,
		1,
		0,
		0,
		28,
		0,
		'',
		'',
		'',
		$soc->default_lang,
		'',
		$payment,
		0,
		'remove_file_comfirm'
	);

    // $filename = dol_sanitizeFileName($payment->ref);
    // $filedir = $conf->cfdixml->multidir_output[$conf->entity] . '/payment/' . dol_sanitizeFileName($payment->ref);
    // $files = scandir($filedir);
    // foreach ($files as $file) {

    //     if ($file === '.' || $file === '..') {
    //     } else {

    //         print '<tr>';
    //         print '<td><a href="' . DOL_MAIN_URL_ROOT . '/document.php?modulepart=cfdixml&file=payment/' . dol_sanitizeFileName($payment->ref) . '/' . $file . '">' . $file . '</a></td>';
    //         print '</tr>';
    //     }
    // }

    //TODO: Foreach documents
    print '</table>';
    print '</div></div>';
}

print '</div>';
print '</div>';



// End of page
llxFooter();
$db->close();
