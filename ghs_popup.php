<?php
//*****************
// This script created and maintained by Andrew Edwards
// CB01.20.19 Phone: x2843
// For: UTS: Safety and Wellbeing
//*****************
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
   "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<link rel="stylesheet" type="text/css" href="style.css" />
<title>Chemical Search Results</title>

<script language="JavaScript">
function getresult()
{
	// Gets selected chemical data (from hidden form elements) and returns to parent window
	
	//workaround code for single result (e.g. no array)
	if (document.ghs_results.result.length == null)
	{
		var name = document.ghs_results.name.value;
		var stmts = document.ghs_results.stmts.value;
		var signl = document.ghs_results.signl.value;
		var pictos = document.ghs_results.pictos.value;
	}	

	for (var i=0; i < document.ghs_results.result.length; i++)
	{
		if(document.ghs_results.result[i].checked)
		{
			var name = document.ghs_results.name[i].value;
			var stmts = document.ghs_results.stmts[i].value;
			var pictos = document.ghs_results.pictos[i].value;
			var signl = document.ghs_results.signl[i].value;
		}
	}
	
	window.opener.document.ghs_build.subst.value=name;
	window.opener.document.ghs_build.stmnt.value=stmts;
	
	// Select correct signal word
	if (signl == "Danger")
		window.opener.document.ghs_build.signl[1].checked=true;
	else if (signl == "Warning")
		window.opener.document.ghs_build.signl[2].checked=true;
	else
		window.opener.document.ghs_build.signl[0].checked=true;
		
	// Select correct pictorgrams
	var chkbxs = window.opener.document.getElementsByName('picto[]');
	var pictarr = pictos.split(',');
	
	for (var a in chkbxs) {
        chkbxs[a].checked=false;
    }
	
	for (var a in pictarr) {
        var pict = pictarr[a];
        chkbxs[pict-1].checked=true;
    }
	
	this.close();
}
</script>

</head>
<body>
<?php

	//init chemical list excelsheet
	require_once './lib/reader.php';
	$chemlist = new Spreadsheet_Excel_Reader();
	$chemlist->setOutputEncoding('CP1251');
	$chemlist->read('./data/GHS_Chemical_List.xls');

	//columns
	$ColCasNo = 1;
	$ColSubstanceName = 2;
	$ColPictoSignal = 4;
	$ColHazardStatements = 6;

	$srchname = $_GET['name'];
	$srchnumb = $_GET['numb'];
	
	echo '<div id="srchbody">';
	
	if ($srchname == "" && $srchnumb == "")
	{
		echo '<p>You must enter either a chemical name or number</p>';
		echo '<p><a href="#" onclick="window.close(); return false">CLOSE WINDOW</a></p>'; 	
	}	
	else	
	{
				
		//search spreadsheet
		$foundrows = array();
		for ($i = 2; $i <= $chemlist->sheets[0]['numRows']; $i++) {
			
			// Sanity Check if substance has a name and number (i.e. if row count is more than actual rows)
			// Nb. !isset() showed up more results, but blank. Couldnt figure out why from spreadsheet, may need further looking if problems
			if (empty($chemlist->sheets[0]['cells'][$i][$ColSubstanceName]) || empty($chemlist->sheets[0]['cells'][$i][$ColCasNo]))
				continue;
			
			$chemname =  $chemlist->sheets[0]['cells'][$i][$ColSubstanceName];
			$casnb =  $chemlist->sheets[0]['cells'][$i][$ColCasNo];
			
			// Remove substances from list with name > 42chars
			// DLJ 31July15 - commented out below, because it is inadvertently also removing substance name where ; is used to separate pseudonyms
			// if (strlen($chemname) > 42)
			//	continue;
			
			// Search algorithms
			if (empty($srchnumb))	
			{
				//search name
				// * = 0 or more
				$pattern = preg_quote($srchname,'/'); 
				$pattern = str_replace( '\*' , '.*', $pattern);   
				if (preg_match( '/' . $pattern . '/' , $chemname ))
					$foundrows[] = $i;
			}	
			else	
			{		
				//search number
				if (stripos($casnb, $srchnumb) !== false )	{
					$foundrows[] = $i;
				}			
			}			
		}
		
		//display results
		
		//print_r($foundrows);
		echo '<form name="ghs_results" >';
		
		// Order results by substance name length
		function sortasc($a,$b){
			// Takes some time with a large amount of searches
			global $chemlist;
			global $ColSubstanceName;
			return strlen($chemlist->sheets[0]['cells'][$a][$ColSubstanceName]) - strlen($chemlist->sheets[0]['cells'][$b][$ColSubstanceName]);
		}
		usort($foundrows,'sortasc');
		
		// Print data pertaining to each result
		foreach($foundrows as $row) {
			
			//initialise
			unset ($stmntstr, $chemstr, $pictosignl, $pieces, $signlword, $pictos, $pictnb);
			
			// Classifications that are not yet available are in the spreadsheet but missing data in some columns. Hide those errors (just print blank fields)
			@$chemstr = $chemlist->sheets[0]['cells'][$row][$ColSubstanceName];
			@$stmntstr = $chemlist->sheets[0]['cells'][$row][$ColHazardStatements];		
			@$pictosignl = $chemlist->sheets[0]['cells'][$row][$ColPictoSignal];
			
			// Tmp vars
			$signlword = "None";
			$pictos  = array();
			
			// The picto and signal word are mixed in one column, need to filter
			if (!empty($pictosignl))
			{
				$pictosignl = preg_replace('#\s+#',',',trim($pictosignl));	//clean up newlines
				$pieces = explode ("," , $pictosignl );
				
				// deal with just one
				if (empty($pieces) && !empty($pictosignl))
					$pieces = array($pictosignl);
					
				foreach ($pieces as $piece)
				{
					// Is it a pictogram
					if (substr($piece, 0, 3) === 'GHS')
					{
						$pictnb = str_replace("GHS", "", $piece);
						array_push($pictos, (int)$pictnb);
					}
					
					// is it a signal word
					if (substr($piece, 0, 1) === '"')
					{
						$signlword = str_replace('"', "", $piece);
					}
				}
			}
			$pictos = implode(',', $pictos);
			
			//echo table form
			echo '<p><input type="radio" name="result" value="'.$row.'">'.$chemstr.'</p>';
			echo '<input type="hidden" name="name" value="'.$chemstr.'" />';
			echo '<input type="hidden" name="stmts" value="'.$stmntstr.'" />';
			echo '<input type="hidden" name="signl" value="'.$signlword.'" />';
			echo '<input type="hidden" name="pictos" value="'.$pictos.'" />';
		}
		echo '</div>';
		echo '<div id="srchbar">';
		//heading
		echo '<b>Search term: </b><i>';
		if ($srchnumb == "")	{	echo $srchname;		}	else	{	echo $srchnumb;		}
		echo '</i>.<br />Select your chemical then press: ';
		echo '<input type="button" name="select" value="select" onclick="getresult(); return false;">';
		echo '</div>';
		echo '</form>';
	}
	
?>



</body>
</html>