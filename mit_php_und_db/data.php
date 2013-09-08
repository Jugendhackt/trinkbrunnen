<?
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

// Daten aus DB auslesen
	 $resultQuery = mysql_query("SELECT Name, Koordinate1, Koordinate2 FROM TrinkbrunnenDB.Trinkbrunnen");
  	 //$n = mysql_num_rows($resultQuery);
  	 echo "{";
  	 while($row = mysql_fetch_row($resultQuery))
						{
						$BrunnenName = htmlspecialchars ($row[0]);
						$Koordi1 = $row[1];
						$Koordi2 = $row[2];
						echo "\"$BrunnenName\" : {\"name\" : \"$BrunnenName\", \"geo\" : { \"lat\" : \"$Koordi1\", \"lon\" : \"$Koordi2\"}}, ";
						//echo $BrunnenName . " at " . $Koordi1 . ", " . $Koordi2; echo"<br>" . "<br>"; 
						//echo "$row[3]"; echo"<br>"; 
						//echo nl2br($row[3]) . "<br>";
						}
	echo "\"\":{}}}";
						
	mysql_close($connection);
						
?>