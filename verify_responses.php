<?php
require_once 'include/config.php';
require_once 'utils/dbutils.php';
require_once 'utils/participation.php';

$checked = 0;
$badsignatures = 0;
$usererrors = 0;
if ($argc < 2){
    echo "Sintaxis: verify_responses.php surveyid\n";
    return 1;
}
$surveyid = $argv[1];

$db = dbConn ();
$responses = $db->prepare ("Select responseid, participantid, response, " . 
    "responsesign FROM {Responses} " .
    "WHERE surveyid = :sid");
$responses->bindParam (":sid", $surveyid, PDO::PARAM_INT);
$responses->execute ();

while ($response = $responses->fetch ()){
    $checked++;
    $responseid = $response['responseid'];
    $participantid = $response['participantid'];
    $res = $response['response'];
    $ressign = $response['responsesign'];
    $participants = $db->prepare ("SELECT publickey FROM {Participants} WHERE " .
        "participantid = :pid LIMIT 1");
    $participants->bindParam (":pid", $participantid, PDO::PARAM_INT);
    $participants->execute ();
    if ($participants->rowCount () == 0){
        echo ("Participant {$participantid} does not exist.\n");
        $participants->closeCursor ();
        $usererrors++;
        continue;
    }
    $pubkey = $participants->fetch ()['publickey'];
    $sign = base64_decode ($ressign);
    //$pk = openssl_pkey_get_public (pubkey);
    if (openssl_verify ($res, $sign, $pubkey, getSignAlgo ()) != 1){
        echo ("Bad signature in response {$responseid}\n");
        $badsignatures++;
    }
}
$responses->closeCursor ();
echo ("All ({$checked}) responses have been checked with {$badsignatures} bad signatures " . 
    "and {$usererrors} non existent users\n");