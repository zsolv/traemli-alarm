<?php
/**
 * Traemli-Alarm - Startpage / Event selection
 * 
 * Marcel Würsten - August 2021
 */
include("config.php");
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
        <div id='yearSelect'>
            <?php
            if(!isset($_GET['year'])){
                $year = date('Y');
            }else{
                $year=filter_var($_GET['year'], FILTER_SANITIZE_NUMBER_INT);
            }
            $first = 1;
            for($i = 0;$i<=(date('Y')-2016);$i++){
                if($first == 0){
                    echo" - ";
                }
                echo"<a href='?year=".(2016+$i)."'>".(2016+$i)."</a>";
                $first = 0;
            }
            ?>
        </div>
        <div id='eventList' class="flexList">
            <?php
            $res = mysqli_query($db,"SELECT `date`, `name`, `value` FROM `events` WHERE `year`= '".mysqli_escape_string($db,$year)."' ORDER BY `date` DESC;");
            while($row = mysqli_fetch_array($res)){
                echo "<a class='flexEl clickable eventWrapper' href='select_kategorie.php?year=".$year."&event=".$row['value']."'><div class='eventDate'>".date("d.m.Y", strtotime($row['date']))."</div><div class='eventName'>".$row['name']."</div></a>\n";
            }

            ?>
        </div>
    </body>
</html>
