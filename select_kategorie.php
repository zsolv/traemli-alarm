<?php
/**
 * Traemli-Alarm - Choose categoriy for for an event
 * 
 * Marcel Würsten - August 2021
 */

// if event or year not given, redirect to start
if(!isset($_GET['year']) || !is_numeric($_GET['year']) || !isset($_GET['event'])){
    header('Location: index.php');
    exit();
}

include("config.php");

// Get params
function getNonDecodedParameters() {
    $a = array();
    foreach (explode ("&", $_SERVER["QUERY_STRING"]) as $q) {
        $p = explode ('=', $q, 2);
        $a[$p[0]] = isset ($p[1]) ? $p[1] : '';
    }
    return $a;
}
$_GET = getNonDecodedParameters();

$year = mysqli_escape_string($db,$_GET['year']);
$event = mysqli_escape_string($db,$_GET['event']);

// If event doesn't exist in given year, redirect to start
if(mysqli_fetch_array(mysqli_query($db,"SELECT COUNT(*) as `c` FROM `events` WHERE `year` = '".$year."' and `value` = '".$event."'"))['c']!=1){
    header('Location: index.php');
    exit();
}

?>
<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width; initial-scale=1;">
        <title>Tr&auml;mli-Alarm</title>
        <link rel="stylesheet" href="style.css" />
    </head>
    <body>
        <div id="header">
            <img src='sign.svg' id="sign">
            <span>Tr&auml;mli-Alarm</span>
        </div>
        <div id="eventInfo">
            <?php
            echo mysqli_fetch_array(mysqli_query($db,"SELECT `name` FROM `events` WHERE `year`= '".$year."' and `value`= '".$event."';"))['name'];
            ?>
        </div>
        <div id="katList" class="flexList">
            <a class="flexEl clickable" href="index.php">&#x27F5; Zurück &#x27F5;</a>
            <?php
            $res = mysqli_query($db,"SELECT `Kategorie`,`Laenge`,`Steigung`,`PoAnz`, (SELECT COUNT(*) FROM `results` WHERE `results`.`event` = `kats`.`event` and `results`.`year` = `kats`.`year` and `results`.`Kategorie` = `kats`.`Kategorie`) as `Teilnehmer` FROM `kats` WHERE `year` = '".$year."' and `event` = '".$event."';");
            while($row = mysqli_fetch_array($res)){
                echo "<a class=\"flexEl clickable\" href='show_kategorie.php?year=".$year."&event=".$event."&kat=".$row['Kategorie']."'>".$row['Kategorie']." - ".$row['Laenge']." km, ".$row['Steigung']." m, ".$row['cnCount']." Posten, ".$row['Teilnehmer']." Teilnehmer</a>\n";
            }
            ?>
        </div>
    </body>
</html>
