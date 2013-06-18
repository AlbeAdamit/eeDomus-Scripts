<?php
/*************************************************************************************
**
** Script "TEMPO vers XML"
**
** Script qui retourne dans des donnnees XML l'etat TEMPO pour le jour courant
** et le lendemain, ainsi que le nombre de jours TEMPO restant
**
** PlaneteDomo - http://github.com/PlaneteDomo/eeDomus-Scripts/
**
**************************************************************************************/


$url="http://particuliers.edf.com/abonnement-et-contrat/les-prix/les-prix-de-l-electricite/option-tempo/la-couleur-du-jour-2585.html";

$page = file_get_contents($url);
           
if(!$page)
{      // pas de réponse d'edf
   die("Pas de réponse");
}
           
if($page !="")  // page reçue
{                               

    //header("Content-type: text/xml"); 
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    echo "<tempo>\n";
    
    // dans le source html d'EDF, on cherche <li class="blue">X</li> ou
    // <li class="white">X</li> ou <li class="red">X</li>
    // la croix indique la bonne réponse, on se limite donc à cherche celle-ci

    // On charge le document et on utilise XPath pour faire la recherche d'information
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($page);
    
    
    $xpath = new DOMXPath($dom);
    
    // Récupération de la couleur du jour
    $my_xpath_query = '//*[@id="ContentTempo"]/div[1]/div[2]/ul[1]/li';
    $result_rows = $xpath->query($my_xpath_query);

    $val = 'undefined';
    if ($result_rows->length!=0) 
    {    
        // On recherche la bonne couleur en fonction de la croix
        foreach ($result_rows as $result_object)
        {
            if (trim($result_object->childNodes->item(0)->nodeValue)=='X')
                $val = $result_object->getAttribute('class');
        }
    }
    
    echo "<aujourdhui>".$val."</aujourdhui>\n";
    
    // Récupération de la couleur du lendemain
    $my_xpath_query = '//*[@id="ContentTempo"]/div[2]/div[2]/ul[1]/li';    
    $result_rows = $xpath->query($my_xpath_query);
     
    $val = 'undefined';
    if ($result_rows->length!=0) 
    {    
        // On recherche la bonne couleur en fonction de la croix
        foreach ($result_rows as $result_object)
        {
            if (trim($result_object->childNodes->item(0)->nodeValue)=='X')
                $val = $result_object->getAttribute('class');
        }
    }
    
    echo "<demain>".$val."</demain>\n";

    $my_xpath_query = '//*[@id="TempoRemainingDays"]/li/strong';
    $result_rows = $xpath->query($my_xpath_query);
    foreach ($result_rows as $result_object)
    {
        $data[] = trim($result_object->childNodes->item(0)->nodeValue);
    }

    echo "<bleu_res>".$data[0]."</bleu_res>\n";
    echo "<bleu_tot>".$data[1]."</bleu_tot>\n";
    echo "<bleu_acc>".($data[1]-$data[0])."</bleu_acc>\n";
    echo "<blanc_res>".$data[2]."</blanc_res>\n";
    echo "<blanc_tot>".$data[3]."</blanc_tot>\n";
    echo "<blanc_acc>".($data[3]-$data[2])."</blanc_acc>\n";
    echo "<rouge_res>".$data[4]."</rouge_res>\n";
    echo "<rouge_tot>".$data[5]."</rouge_tot>\n";
    echo "<rouge_acc>".($data[5]-$data[4])."</rouge_acc>\n";
    echo "</tempo>";
}

?>