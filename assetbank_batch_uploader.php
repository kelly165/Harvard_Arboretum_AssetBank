<?php
/*
Kelly Chen 2022
Arnold Arboretum of Harvard university has a digital system called Asset Bank. 
One of its collections houses plant seed images. With the sole exception of image names, there is no plant metadata asociated with these images.
This project is a proposed method to provide a web interface through which staff memebers can upload a text file containing 
a list of AssetBank ids and plant seed image names. The script extracts the plant ids from this file using regular expression.
After getting the plant id, the script connects to the plant info datbase to get the plant's family, genus, scientific name, cultivar, etc.
It then generates a file that can be used to batch upload the info back into AssetBank to populate metadata.


*/

$cgi_method = getenv("REQUEST_METHOD");
$html_header =<<<EOT
<!DOCTYPE html>
<html>
<header>
<title> Plant Seed Image Batch Upload File Generator</title>
<style>
	body {background-color: #CCFFCC;}
	h1   {text-align: center;}
	h2   {text-align: center;}

</style>

<script>
function confirmation() {
	confirm("Depending on file size, processing time will vary");
}	
</script>
</header>
<body>
<h1>Arnold Arboretum AssetBank </h1>
<h2>Plant Seed Image Batch Upload File Generator</h2>
EOT;

if ($cgi_method == "GET") {
	print $html_header;
	print <<<EOT
Please upload a tab delimited text file downloaded from AssetBank with Asset Ids and file names.
<br /><br />
<form enctype = "multipart/form-data" method = "post" onSubmit="return confirmation()">
  <label for="myfile">Select a file:</label>
  <input type="file" id="myfile" name="myfile">
  <input type="submit" value="Submit">
</form>
</body>
</html>
EOT;

} else {

	//get upload file
    $uploaded_file = $_FILES['myfile']['tmp_name'];
    if (!file_exists($uploaded_file) ) {  // if it does not exist, exit
       print "<p>please upload a tab delimited text file </p>";
       exit;

    }
    $timestamp = date('Y-m-d-H-i-s');
	
	//if uploaded file can be opened
    if ($file = fopen($uploaded_file, "r")) {
		//connect to database
		$db = new mysqli("localhost","root","","plantdata");
        $line_count = 0;
		//open the output file and write column headers
		$file2 = fopen("output_" . $timestamp. ".txt", "w");
		$header_column = "assetId\tatt:Plant Accession Number and Qualifier:764\tatt:Scientific Name:746\tatt:Plant Name HTML:741\tatt:Family:743\tatt:Genus:744\tatt:Species:745\tatt:Cultivar:763\n";
		fwrite($file2, $header_column);
		
		//process each line
		while (!feof($file)) {
            $line = fgets($file);
			$line_count++;
            $line = str_replace("\r", '', $line);
			$taxon = "";
			if (trim($line)) {
				$line_arr = explode('	', $line);
				if(is_numeric($line_arr[1])){
					$asset_id = $line_arr[1];
					
					//extract plant id using reguar expression
					$id_line = $line_arr[2];
					preg_match("/(\d+[^_]*)/", $id_line, $matches); 
					$plant_id = $matches[1];

	
					//query the plant database
					$sql = "SELECT p.acc_num_and_qual, p.name, p.scientific_name, p.name_html, p.family, p.genus, p.species, p.cultivar ".
					"FROM plant_from_gis p WHERE replace(p.acc_num_and_qual, '*', '-') = '$plant_id'";
					if(!$result = $db->query($sql)){
						print "sql failed " . $sql;
						exit;
					}
					if($result->num_rows > 0){
						while($row = $result ->fetch_assoc()){
							fwrite($file2, $asset_id . "\t" . $row['acc_num_and_qual'] . "\t" . $row['scientific_name'] . "\t" . $row['name_html'] . "\t" .
							$row['family'] . "\t" . $row['genus'] . "\t" . $row['species'] . "\t" . $row['cultivar'] . "\n");
						}
						//print $asset_id . "<br />";
					}
					
				}
				
			}
		}
		fclose($file2);
		fclose($file);
		//print "Process Complete";
       
	   
	   
	    if (file_exists("output_" . $timestamp. ".txt")) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.basename("output_" . $timestamp. ".txt").'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize("output_" . $timestamp. ".txt"));
			readfile("output_" . $timestamp. ".txt");
			exit;
		} else {
			print "File not found";
			exit;
		}
		
    }
	//end of post
	//print "</body>";
	//print "</html>";
}
?>