<?php
/**
 * Traemli-Alarm - Show results in a category of an event
 * 
 * Marcel Würsten - August 2021
 */

// if year, event or category not given, back to start
if(!isset($_GET['year']) || !is_numeric($_GET['year']) || !isset($_GET['event']) || !isset($_GET['kat'])){
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
$kat = mysqli_escape_string($db,$_GET['kat']);

// If event with category doesn't exist in given year, redirect to start
if(mysqli_fetch_array(mysqli_query($db,"SELECT COUNT(*) as `c` FROM `kats` WHERE `year` = '".$year."' and `event` = '".$event."' and `Kategorie`= '".$kat."'"))['c']!=1){
    header('Location: index.php');
    exit();
}

// Convert seconts to hh:mm:ss/h:mm:ss or mm:ss depending on input
function toHHMMSS($sec){
    $h = floor($sec/3600);
    $m = floor($sec/60)%60;
    $s = $sec%60;

    return $h>0?sprintf('%d:%02d:%02d',$h,$m,$s):sprintf('%d:%02d',$m,$s);
}

?>
<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width; initial-scale=1;">
        <title>Trämli-Alarm</title>
        <link rel="stylesheet" href="style.css" />
    </head>
    <body>
        <div id="header">
            <img src='sign.svg' id="sign">
            <span>Trämli-Alarm</span>
        </div>
        <div id="eventInfo"><?php
            echo mysqli_fetch_array(mysqli_query($db,"SELECT `name` FROM `events` WHERE `year`= '".$year."' and `value`= '".$event."';"))['name']." - ".$kat;
        ?>
        </div>
        <div id="athleteList" class="flexList">
            <a class="flexEl clickable" href="select_kategorie.php?year=<?php echo $year;?>&event=<?php echo $event;?>">&#x27F5; Zurück &#x27F5;</a>
            <?php
            $res = mysqli_query($db, "SELECT * FROM `results` WHERE `year`='".$year."' and `event`='".$event."' and `Kategorie` = '".$kat."';");
            while($row = mysqli_fetch_array($res)){  
                echo " <div class=\"flexEl\">\n";
                echo "  <div class=\"athleteMain\">\n";
                echo "   <div class=\"athleteRang\">".($row['Zeit']>0?$row['Rang']:'')."</div>\n";
                echo "   <div class=\"athleteName\">".$row['Name']."</div>\n";
                echo "   <div class=\"athleteTime\">".($row['Zeit']==null?'':toHHMMSS($row['Zeit']))."</div>\n";
                echo "  </div>\n";
                echo "  <div class=\"athleteTram\"><img src='tram.svg.php?year=".$year."&event=".$event."&kat=".$kat."&athlete=".$row['index']."' class='tram_svg'></div>\n";
                echo " </div>\n";
            }
            ?>
        </div>
    </body>
</html>
