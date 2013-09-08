<html>
<title>M&uumlnchner Trinkbrunnen</title>
<head>

</head>
<body>
<?php


//Initialisierung
ini_set( "memory_limit","2000M");
$host = 'localhost';
	 $user = 'root';
	 $pass3 = '';
		
		
	 // Verbindung zum Datenbankserver herstellen
  	 $connection = mysql_connect($host, $user, $pass3);
  	 if(!$connection) {
   	 	die("Verbindung zum Datenbankserver konnte nicht hergestellt werden.<br />");
  	 }
  	 //echo "Verbindung zum Datenbankserver konnte hergestellt werden.<br />";
  				
  	 // Datenbank auswählen 
  
  	 $database = "TrinkbrunnenDB";
  	 $selection = mysql_select_db($database, $connection);
  	 if(!$selection){
  	 	die("Verbindung zur Datenbank konnte nicht hergestellt werden.<br />");
  	 }
  	 //else{echo "Verbindung zur Datenbank konnte hergestellt werden.<br />";	} 
require_once 'Services/OpenStreetMap.php';
$osm = new Services_OpenStreetMap();
$osm->loadXml("/tmp/munich-fountains.osm");
//$osm->loadXml("/tmp/berlin-fountains.osm");
//$osm->get(48.0616,11.3612, 48.2494,11.7231);
//file_put_contents("/tmp/osm.osm", $osm->getXml());

//$osm = new Services_OpenStreetMap();
echo"blah1";
//$osm->loadXml("/tmp/osm.osm");
echo"blah";
$results = $osm->search(array("drinkable" => "yes"));
//$results = $osm->search(array("amenity" => "fountain"));
echo "List of Fountains\n";
echo "==================\n\n";

//var_dump($results);
      
foreach ($results as $result) {
    //$name = "hallo";
    /*$addr_street = null;
    $addr_city = null;
    $addr_country = null;
    $addr_housename = null;
    $addr_housenumber = null;
    $opening_hours = null;
    $phone = null;*/
    //echo $result;
    
    //var_dump($result);
    if(($result->getTag("name") == " ") OR ($result->getTag("name") == "")) {
    	$name1 = "Name not available";}
    	else {
    $name1 = htmlspecialchars ($result->getTag("name"));}
    $Koor1 = $result->getLat();
    $Koor2 = $result->getLon();
	 //echo ($result->getTag("name"));
	 //echo "<br>";
	 //echo ($result->getLat());
	 //echo "<br>";
	 //echo ($result->getLon());
	 // Brunnen in Datenbank einlesen	 
	 
	 $result = mysql_query("SELECT Name, Koordinate1, Koordinate2 FROM TrinkbrunnenDB.Trinkbrunnen WHERE Name='$name1'AND Koordinate1 = '$Koor1' AND Koordinate2 = '$Koor2'");
  	 if($result) {$query = mysql_query("INSERT INTO TrinkbrunnenDB.Trinkbrunnen (Name, Koordinate1, Koordinate2) VALUES ('$name1', '$Koor1', '$Koor2')");
  	 $result1 = mysql_query($query);}
	 
	 
    }
    
    // Daten aus DB auslesen
	 $resultQuery = mysql_query("SELECT Name, Koordinate1, Koordinate2 FROM TrinkbrunnenDB.Trinkbrunnen");
  	 //$n = mysql_num_rows($resultQuery);
  	 
  	 while($row = mysql_fetch_row($resultQuery))
						{
						$BrunnenName = $row[0];
						$Koordi1 = $row[1];
						$Koordi2 = $row[2];
						echo $BrunnenName . " at " . $Koordi1 . ", " . $Koordi2; echo"<br>" . "<br>"; 
						//echo "$row[3]"; echo"<br>"; 
						//echo nl2br($row[3]) . "<br>";
						}
  	 
  	 //foreach($resultQuery as $res){
  	 //	var_dump($res);	
  	 //}
    mysql_close($connection);
    
?>
</body>
</html>