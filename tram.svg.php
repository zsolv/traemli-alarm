<?php
/**
 * Traemli-Alarm - Graphics
 * 
 * Marcel Würsten - August 2021
 */
header('Content-type: image/svg+xml');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// if data not set, return 404
if(!isset($_GET['year']) || !is_numeric($_GET['year']) || !isset($_GET['event']) || !isset($_GET['kat']) || !isset($_GET['athlete']) || !is_numeric($_GET['athlete'])){
    http_response_code(404);
    exit();
}

include("config.php");
include("hsv.php");

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
$athleteIndex = mysqli_escape_string($db,$_GET['athlete']);


// If runner at given event in given categoriy doesn't exist in given year, return 404
if(mysqli_fetch_array(mysqli_query($db,"SELECT COUNT(*) as `c` FROM `results` WHERE `year` = '".$year."' and `event` = '".$event."' and `Kategorie`= '".$kat."' and `index` = '".$athleteIndex."'"))['c']!=1){
    http_response_code(404);
    exit();
}

// Set frame to 15s
$traemliFrame = 15;

// Get the amount of controls
$cnCountSql = mysqli_query($db,"SELECT `PoAnz` FROM `kats` WHERE `year`='".$year."' and `event`='".$event."' and `Kategorie` = '".$kat."';") or die(mysqli_error($db));
$cnCount = mysqli_fetch_array($cnCountSql)['PoAnz'];

// Get the splits of the requested athlete
$athleteSplitsSql = mysqli_query($db,"SELECT `nr`,`cn`,`time` FROM `results-splits` WHERE `year`='".$year."' and `event`='".$event."' and `index` = '".$athleteIndex."'") or die(mysqli_error($db));
$athleteSplits = array();
while($row = mysqli_fetch_array($athleteSplitsSql)){
    $athlete['nr'] = $row['nr'];
    $athlete['cn'] = $row['cn'];
    $athlete['time'] = $row['time'];
    $athleteSplits[$row['nr']] = $athlete;
}

// Get all result data for requested athlete
$athlete = mysqli_fetch_array(mysqli_query($db, "SELECT * FROM `results` WHERE `year`='".$year."' and `event`='".$event."' and `index` = '".$athleteIndex."'"));


// Generate SQL request to return trams
$tram_sql = "SELECT `rs1`.`index` as `index`, `results`.`Name` as `Name`,`results`.`Kategorie` as `Kategorie`, `rs1`.`cn` as `cn1`,`rs1`.`time` as `t1`,`rs2`.`cn` as `cn2`,`rs2`.`time` as `t2` FROM `results-splits` as `rs1`,`results-splits` as `rs2`, `results`  WHERE `rs1`.`year` = `rs2`.`year` and `results`.`year` = `rs1`.`year` and `rs1`.`index` = `rs2`.`index` and `results`.`index` = `rs1`.`index` and `rs1`.`event`= `rs2`.`event` and `results`.`event` = `rs1`.`event`and `rs1`.`year`='".$year."' and `rs1`.`event`='".$event."' and `rs1`.`nr` + 1 = `rs2`.`nr` and (";
$addPo = false;
for($i = 0; $i<$cnCount; ++$i){
    if($athleteSplits[$i]['time'] != 0 && $athleteSplits[$i+1]['cn'] != 0){
        $addPo = true;
        $tram_sql = $tram_sql. "(`rs1`.`cn` = '".$athleteSplits[$i]['cn']."' and `rs2`.`cn` = '".$athleteSplits[$i+1]['cn']."' and `rs1`.`time` > '".($athleteSplits[$i]['time']-$traemliFrame)."' and `rs1`.`time` < '".($athleteSplits[$i]['time']+$traemliFrame)."' and `rs2`.`time` > '".($athleteSplits[$i+1]['time']-$traemliFrame)."' and `rs2`.`time` < '".($athleteSplits[$i+1]['time']+$traemliFrame)."')  or";
    }
}
if($addPo){
    $tram_sql = substr($tram_sql, 0, -3).") and `rs1`.`index` <> '".$athleteIndex."' ORDER BY `rs1`.`index`, `rs1`.`time` ASC";
}else{
    // if something irregular happens, return error as svg
    echo '<?xml version="1.0" encoding="utf-8" ?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" viewBox="0 0 350 30" preserveAspectRatio="xMidYMid meet"><text x="0" y="20" font-size="20px" fill="#000000" font-family="sans-serif">Fehler - Tramsuche nicht möglich!</text></svg>';
    exit();
}

// Generate array with control data from requested athletes splits
$data['controls'] = array();
for($i = 0; $i <= ($cnCount+1); ++$i){
    $data['controls'][$i]['cn'] = $athleteSplits[$i]['cn'];
    $data['controls'][$i]['ok'] = $athleteSplits[$i]['time']==0?'false':'true';
}

// Request huge sql from database with further details
$tram_res = mysqli_query($db, $tram_sql);

// Function to return split position in course of requested athlete for given tram
$cnsToNr = function($athleteSplits, $cn1, $cn2, $t1, $t2){
    global $traemliFrame;
    for($i = 0; $i<count($athleteSplits);++$i){
        if($athleteSplits[$i]['cn'] == $cn1 && $athleteSplits[$i+1]['cn'] == $cn2 && ($athleteSplits[$i]['time']-$traemliFrame) <= $t1  && ($athleteSplits[$i]['time']+$traemliFrame) >= $t1 && ($athleteSplits[$i+1]['time']-$traemliFrame) <= $t2  && ($athleteSplits[$i+1]['time']+$traemliFrame) >= $t1){
            return $i;
        }
    }
};

// Define arrays for trams
$trams = array();
$competitors = array();
while($row = mysqli_fetch_array($tram_res)){

    // Fill values from sql dump into array
    $tram['name'] = $row['Name'];
    $tram['kat'] = $row['Kategorie'];
    $tram['id'] = $row['index'];
    $tram['from'] = $cnsToNr($athleteSplits, $row['cn1'],$row['cn2'],$row['t1'],$row['t2']);
    $tram['from-cn'] = $row['cn1'];
    $tram['to-cn'] =  $row['cn2'];
    $tram['from-diff'] = ($row['t1']-$athleteSplits[$tram['from']]['time']);
    $tram['to-diff'] = ($row['t2']-$athleteSplits[$tram['from']+1]['time']);
    $tram['from-runner'] = $athleteSplits[$tram['from']]['time'];
    $tram['to-runner'] = $athleteSplits[$tram['from']+1]['time'];
    $tram['from-tram'] = $row['t1'];
    $tram['to-tram'] = $row['t2'];
    
    // Add array to list of trams
    $trams[] = $tram;
    

    $foundInCompetitors = false;
    for($i = 0; $i < count($competitors); ++$i){
        if($competitors[$i]['index'] == $row['index']){
            $foundInCompetitors = true;
            if(!in_array($tram['from'],$competitors[$i]['controls'])){
                $competitors[$i]['controls'][] = $tram['from'];
            }
            if(!in_array($tram['from']+1,$competitors[$i]['controls'])){
                $competitors[$i]['controls'][] = $tram['from']+1;
            }
        }
    }
    if(!$foundInCompetitors){
        $h = array();
        $h[] = $tram['from'];
        $h[] = $tram['from']+1;
        $had['index'] = $row['index'];
        $had['name'] = $row['Name'];
        $had['kat'] = $row['Kategorie'];
        $had['controls'] = $h;
        $competitors[] = $had;
    }
    
}

// iterate over competitors
for($i = 0; $i<count($competitors);++$i){

    // add line after tram to tram, if the same control follows
    for($j = 0; $j<count($competitors[$i]['controls'])-1; ++$j){
        if($competitors[$i]['controls'][$j] > 0 && ($competitors[$i]['controls'][$j] + 1) == $competitors[$i]['controls'][$j+1] && !in_array($competitors[$i]['controls'][$j] - 1, $competitors[$i]['controls'])&& $athleteSplits[$competitors[$i]['controls'][$j] - 1]['time']>0){
            $res = mysqli_query($db,"SELECT `rs0`.`time` as `t0`, `rs1`.`time` as `t1` FROM `results-splits` AS `rs0`, `results-splits` AS `rs1`, `results-splits` AS `rs2` WHERE `rs1`.`year` = `rs2`.`year` and `rs0`.`year` = `rs1`.`year` and `rs1`.`index` = `rs2`.`index` and `rs0`.`index` = `rs1`.`index` and `rs1`.`event`= `rs2`.`event` and `rs0`.`event` = `rs1`.`event`and `rs1`.`year`='".$year."' and `rs1`.`event`='".$event."' and `rs0`.`nr` + 1 = `rs1`.`nr` and `rs1`.`nr` + 1 = `rs2`.`nr` and `rs0`.`cn` = '".$athleteSplits[$competitors[$i]['controls'][$j]-1]['cn']."' and `rs1`.`cn` = '".$athleteSplits[$competitors[$i]['controls'][$j]]['cn']."' and `rs2`.`cn` = '".$athleteSplits[$competitors[$i]['controls'][$j]+1]['cn']."' and `rs0`.`time` <> '0' and `rs0`.`index` = '".$competitors[$i]['index']."';");

            while($row = mysqli_fetch_array($res)){
                $tram['name'] = $competitors[$i]['name'];
                $tram['kat'] = $competitors[$i]['kat'];
                $tram['id'] = $competitors[$i]['index'];
                $tram['from'] = $competitors[$i]['controls'][$j]-1;
                $tram['from-cn'] = $athleteSplits[$competitors[$i]['controls'][$j]-1]['cn'];
                $tram['to-cn'] = $athleteSplits[$competitors[$i]['controls'][$j]]['cn'];
                $tram['from-diff'] = ($row['t0']-$athleteSplits[$tram['from']]['time']);
                $tram['to-diff'] = ($row['t1']-$athleteSplits[$tram['from']+1]['time']);
                $tram['from-runner'] = $athleteSplits[$tram['from']]['time'];
                $tram['to-runner'] = $athleteSplits[$tram['from']+1]['time'];
                $tram['from-tram'] = $row['t0'];
                $tram['to-tram'] = $row['t1'];
            
                $trams[] = $tram;
            }
        }
    }

    // add line before tram to tram, if the same control before
    for($j = 1; $j<count($competitors[$i]['controls']); ++$j){
        if($athleteSplits[$competitors[$i]['controls'][$j]]['cn'] > 30 && $competitors[$i]['controls'][$j-1] + 1 == $competitors[$i]['controls'][$j] && !in_array($competitors[$i]['controls'][$j] + 1, $competitors[$i]['controls']) && $athleteSplits[$competitors[$i]['controls'][$j] + 1]['time']>0){
            $res = mysqli_query($db,"SELECT `rs1`.`time` as `t1`, `rs2`.`time` as `t2` FROM `results-splits` AS `rs0`, `results-splits` AS `rs1`, `results-splits` AS `rs2` WHERE `rs1`.`year` = `rs2`.`year` and `rs0`.`year` = `rs1`.`year` and `rs1`.`index` = `rs2`.`index` and `rs0`.`index` = `rs1`.`index` and `rs1`.`event`= `rs2`.`event` and `rs0`.`event` = `rs1`.`event`and `rs1`.`year`='".$year."' and `rs1`.`event`='".$event."' and `rs0`.`nr` + 1 = `rs1`.`nr` and `rs1`.`nr` + 1 = `rs2`.`nr` and `rs0`.`cn` = '".$athleteSplits[$competitors[$i]['controls'][$j]-1]['cn']."' and `rs1`.`cn` = '".$athleteSplits[$competitors[$i]['controls'][$j]]['cn']."' and `rs2`.`cn` = '".$athleteSplits[$competitors[$i]['controls'][$j]+1]['cn']."' and `rs2`.`time` <> '0' and `rs0`.`index` = '".$competitors[$i]['index']."';");
            while($row = mysqli_fetch_array($res)){
                $tram['name'] = $competitors[$i]['name'];
                $tram['kat'] = $competitors[$i]['kat'];
                $tram['id'] = $competitors[$i]['index'];
                $tram['from'] = $competitors[$i]['controls'][$j];
                $tram['from-cn'] = $athleteSplits[$competitors[$i]['controls'][$j]]['cn'];
                $tram['to-cn'] = $athleteSplits[$competitors[$i]['controls'][$j]+1]['cn'];
                $tram['from-diff'] = ($row['t1']-$athleteSplits[$tram['from']]['time']);
                $tram['to-diff'] = ($row['t2']-$athleteSplits[$tram['from']+1]['time']);
                $tram['from-runner'] = $athleteSplits[$tram['from']]['time'];
                $tram['to-runner'] = $athleteSplits[$tram['from']+1]['time'];
                $tram['from-tram'] = $row['t1'];
                $tram['to-tram'] = $row['t2'];
            
                $trams[] = $tram;
            }
        }
    }

    // Assign each competitor an unique color around hsv
    for($j = 0; $j<count($trams);++$j){
        if($competitors[$i]['index'] == $trams[$j]['id']){
            $trams[$j]['color'] = "#".fGetRGB((360/(count($competitors)+1))*($i+1), 100, 80);
            $competitors[$i]['color'] = "#".fGetRGB((360/(count($competitors)+1))*($i+1), 100, 80);
        }
    }


}

$data['tram'] = $trams;


// If no tram was found, return this as svg
if(count($trams) == 0){
    echo '<?xml version="1.0" encoding="utf-8" ?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" viewBox="0 0 200 30" preserveAspectRatio="xMidYMid meet"><text x="0" y="20" font-size="20px" fill="#000000" font-family="sans-serif">Kein Tram gefunden!</text></svg>';
    exit();
}

// Draw svg with gathered data
?>
<?php echo '<?xml version="1.0" encoding="utf-8" ?>';?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" viewBox="0 0 700 <?php echo ((($cnCount+2)*20)+40)?>" preserveAspectRatio="xMidYMid meet">
<!-- Define Background -->
<rect width="700" height="<?php echo ((($cnCount+2)*20)+40)?>" fill="#f9f9f9"></rect>

<!-- Draw startline -->
<text x="20" y="30" dominant-baseline="middle" fill="#000000" font-family="sans-serif">Start</text>
<line x1="100" y1="30" x2="400" y2="30" stroke-width="1" stroke="#cccccc"></line>

<!-- draw a line for each control -->
<?php
for($i = 1; $i<=$cnCount; ++$i){
        echo '<text x="20" y="'.(($i*20)+30).'" dominant-baseline="middle" fill="#000000" font-family="sans-serif">'.$i." (".$data['controls'][$i]['cn'].")".'</text>'."\n";
    if($data['controls'][$i]['ok'] == 'true'){
        echo '<line x1="100" y1="'.(($i*20)+30).'" x2="400" y2="'.(($i*20)+30).'" stroke-width="0.5" stroke="#cccccc"></line>'."\n";
    }else{
        echo '<line x1="100" y1="'.(($i*20)+30).'" x2="400" y2="'.(($i*20)+30).'" stroke-width="0.5" stroke="#ff7777"></line>'."\n";
    }
}
?>

<!-- draw finish line -->
<text x="20" y="<?php echo (($cnCount+2)*20)+10?>" dominant-baseline="middle" fill="#000000" font-family="sans-serif">Ziel</text>
<line x1="100" y1="<?php echo ((($cnCount+2)*20)+10)?>" x2="400" y2="<?php echo ((($cnCount+2)*20)+10)?>" stroke-width="1" stroke="#cccccc"></line>

<!-- Write the runners name in the top center and draw middle line -->
<text x="250" y="20" text-anchor="middle" fill="#ff0000" font-family="sans-serif" font-size="8px"><?php echo htmlspecialchars(explode(" ",$athlete['Name'])[0], ENT_XML1, 'UTF-8')?></text>
<line x1="250" y1="30" x2="250" y2="<?php echo ((($cnCount+2)*20)+10)?>" stroke-width="1" stroke="#ff0000"></line>

<!-- draw the -10, -5, 5, 10 s lines -->
<line x1="150" y1="25" x2="150" y2="<?php echo ((($cnCount+2)*20)+15)?>" stroke-width="0.5" stroke="#cccccc"></line>
<line x1="200" y1="25" x2="200" y2="<?php echo ((($cnCount+2)*20)+15)?>" stroke-width="0.5" stroke="#cccccc"></line>
<line x1="300" y1="25" x2="300" y2="<?php echo ((($cnCount+2)*20)+15)?>" stroke-width="0.5" stroke="#cccccc"></line>
<line x1="350" y1="25" x2="350" y2="<?php echo ((($cnCount+2)*20)+15)?>" stroke-width="0.5" stroke="#cccccc"></line>
<text x="120" y="20" fill="#000000" font-family="sans-serif" font-size="10px">10s dahiner</text>
<text x="195" y="20" fill="#000000" font-family="sans-serif" font-size="10px">5s</text>
<text x="295" y="20" fill="#000000" font-family="sans-serif" font-size="10px">5s</text>
<text x="320" y="20" fill="#000000" font-family="sans-serif" font-size="10px">10s voraus</text>

<!-- draw tram lines -->
<?php
    
for($i = 0; $i<count($trams);++$i){

    $from_line_x = -10*$trams[$i]['from-diff'];
    $to_line_x = -10*$trams[$i]['to-diff'];
    $from_line_y = (20*$trams[$i]['from']) + 30;
    $to_line_y = (20*$trams[$i]['from']) + 50;
    
    // If line would go out of drawing area, shorten to corresponding coordinates
    if($from_line_x > 150){
        $from_line_y = ($to_line_y - ((150-$to_line_x) / (($from_line_x - $to_line_x )/20)));
        $from_line_x = 150;
    }
    if($from_line_x < -150){
        $from_line_y = ($to_line_y - ((-150-$to_line_x) / (($from_line_x - $to_line_x )/20)));
        $from_line_x = -150;
    }
    if($to_line_x < -150){
        $to_line_y = ($from_line_y - ((-150-$from_line_x) / (($from_line_x - $to_line_x )/20)));
        $to_line_x = -150;
    }
    if($to_line_x > 150){
        $to_line_y = ($from_line_y - ((150-$from_line_x) / (($from_line_x - $to_line_x )/20)));
        $to_line_x = 150;
    }
    
    $from_line_x += 250; // offset to center
    $to_line_x += 250;   // offset to center
    
    echo '<line x1="'.$from_line_x.'" y1="'.$from_line_y.'" x2="'.$to_line_x.'" y2="'.$to_line_y.'" stroke-width="1" stroke="'.$trams[$i]['color'].'"></line>'."\n";
}

// List all competitors
for($i = 0; $i<count($competitors); ++$i){
    echo '<text x="420" y="'.(($i*20)+40).'" fill="'.$competitors[$i]['color'].'" font-family="sans-serif">'.htmlspecialchars($competitors[$i]['name'], ENT_XML1, 'UTF-8')." (".$competitors[$i]['kat'].")".'</text>'."\n";
}
    
?>
</svg>
