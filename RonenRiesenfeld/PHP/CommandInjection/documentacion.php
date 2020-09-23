<?php
session_name('bakoshe');
session_start();

//****** BEGIN: SECURITY CHECK  *******************************
include_once('lib/Security.php');

if (!Security::isAllowed()) {
    session_unset();     
    session_destroy();
    
    header('Location: logout.php?portal=cimort');
    return;
}
$_SESSION['LAST_ACTIVITY'] = time();

//****** END: SECURITY CHECK  *******************************

header('Expires: 0');
header('Pragma: no-cache');

include_once('lib/Bakoshe.php');

if ($_SESSION['redirect'] != "") {
    header('Location: ' . $_SESSION['redirect']);
    return;   
}

$g_bakid=$_SESSION['bakid'];

include_once("lib/Documentacion.php");

if (!defined('ENT_SUBSTITUTE')) {
    define('ENT_SUBSTITUTE', 8);
}


$db = new DB;

$error = false;
$uploadOk=1;
$errorMsg =array();

if (isset($_POST['save']) && $_POST['save']=="Guardar e ir al Panel" ) {

    $target_dir = "archivos/";

    $numDoctos = count($_FILES['documentos']['name']);

    $docto = new Documentacion;
    $docto->clean($g_bakid);


    $sql = "SELECT id, descripcion, obligatorio, partidas "
    . " FROM catalogo_doctos order by id asc ";

    $result = $db->openRS($sql);
    $htmlIdx = 0;
    while ( ($row = $db->fetchRow($result)) && !$error) {
     
        for ($partida=0;$partida < $row->partidas && !$error;$partida++) { 
   
//for ($i=0;$i<$numDoctos && !$error;$i++) {
            $errorMsg[$row->id][$partida]="";
            $archivo="";
            
            if ($_FILES["documentos"]["name"][$htmlIdx] == "") {
                $errorMsg[$row->id][$partida]="";
                if ($_POST['archivo'][$htmlIdx] != "" ) {
                    $archivo =$_POST['archivo'][$htmlIdx];
                }
            } else {
                $target_file = $target_dir . $g_bakid . "_" . preg_replace('/[^A-Za-z0-9_\.\-]/', '_', basename($_FILES["documentos"]["name"][$htmlIdx]));
                $recordFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
            
                // Allow certain file formats
                if($recordFileType != "pdf" && $recordFileType != "png" && $recordFileType != "jpg"
                   && $recordFileType != "doc" && $recordFileType != "docx" && $recordFileType != "jpeg") {
                    $errorMsg[$row->id][$partida] = "Solo archivos pdf, doc, docx, jpeg, png o jpg son permitidos.";
                    $uploadOk = 0;
                }
                // Check if $uploadOk is set to 0 by an error
                if ($uploadOk == 0) {
                    $errorMsg[$row->id][$partida] .="<br>El archivo no fue subido";
                // if everything is ok, try to upload file
                } else {
                    
                    if (move_uploaded_file($_FILES["documentos"]["tmp_name"][$htmlIdx], $target_file)) {
                        $errorMsg[$row->id][$partida] .="<br>El archivo ". basename( $_FILES["documentos"]["name"][$htmlIdx]). " ha sido subido.";
                        $archivo=preg_replace('/[^A-Za-z0-9_\.\-]/', '_',basename( $_FILES["documentos"]["name"][$htmlIdx]));
                        exec("/usr/bin/aescrypt -e -k /data/home/noone/bakoshe.key " . $target_file);
                        unlink ($target_file);
                        exec("/bin/chmod 600 " . $target_file . ".aes");
                    } else {
                        $errorMsg[$row->id][$partida] .="<br>Hubo un error procesando el archivo.";
                        $error = !$docto->add($g_bakid,$row->id,$partida,"",$_POST['razon'][$htmlIdx]);
                        $uploadOk = 0;
                    }
                }
                
            } 
            $error = !$docto->add($g_bakid,$row->id,$partida,$archivo,$_POST['razon'][$htmlIdx],$_POST['clave'][$htmlIdx]);
            $htmlIdx++;
        }        
    }
    
    $db->closeRS($result);
    
    $docto = null;
    if ($uploadOk == 1 && !$error) {
        header("Location: panel.php");
        return;

    }

      
}


$sql = "SELECT id, descripcion, obligatorio, partidas, clave "
. " FROM catalogo_doctos ";

$result = $db->openRS($sql);
while ($row = $db->fetchRow($result)) {
    for ($i=0; $i < $row->partidas; $i++) {
        $archivo[$row->id][$i] ="";
        $razon[$row->id][$i] ="";
        $clave[$row->id][$i] ="";
        $errorMsg[$row->id][$i]="";
    }
}

$db->closeRS($result);



$sql = "select cd.id, cd.descripcion, trim(d.archivo) archivo, d.razon, cd.partidas, d.clave "
        . " from catalogo_doctos cd left join documentos d on cd.id=d.docto_id and d.bakid=" . $g_bakid
        . " order by cd.id asc";

$result = $db->openRS($sql);
$current=0;
$partida=0;
while ($row = $db->fetchRow($result)) {
    if ($current != $row->id) {
        $current = $row->id;
        $partida=0;
    } else {
        $partida++;
    }
    
    $archivo[$row->id][$partida] =$row->archivo;
    $razon[$row->id][$partida] =$row->razon;
    $clave[$row->id][$partida] =$row->clave;
    
    if ($uploadOk == 1) {
        $errorMsg[$row->id][$partida]="";
    }
}




?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link href="bakoshe.css" rel="stylesheet" type="text/css" />
    <link href="style.css" rel="stylesheet" type="text/css" />
    <script src="includes/gui.js" language="javascript"></script>
    
    <title>Solicitud de Subsidio de Colegiatura</title>

</head>

<body language="javascript" onload="start();">
<div class="content">

    <?php include_once("header.php");
        include_once("header_panel.php");
    ?>
    
   <p class="required containerLeftIndent">
Requerimos se anexe TODA la informaci&oacute;n que se solicita, lo cual permitir&aacute; una evaluaci&oacute;n
correcta  y equitativa de su solicitud.<br>
Anexar los siguientes documentos o indicar detalladamente la raz&oacute;n por la cual no se anexa.<br>
Solo archivos PDF, DOC, DOCX, JPG y PNG son aceptados.<br><br>
Nota: Recuerda que a los 20 min sin actividad la sesion terminar&aacute;
   
   </p>

    <form name="documentacion" id="documentacion" action="documentacion.php" method="post" enctype="multipart/form-data" autocomplete>

   <h3 class="title">Documentos</b></h3>
    <table class="mytable"   cellspacing="0" cellpadding="0" >
    <tr>
        <td class="outerBG">
        <table width="100%" border="0" cellspacing="1" cellpadding="4" ><!--rules="rows"-->
            <tr>
                <td>
                    <label class="tableH">Documento</label>
                </td>
                <td>
                    <label class="tableH">Archivo</label>
                </td>
                <td>
                    <label class="tableH">Cargar</label>
                </td>
                <td>
                    <label class="tableH">Clave</label>
                </td>
                <td>
                    <label class="tableH">En caso de no proporcionarlo <br> indicar la raz&oacute;n</label>
                </td>
            </tr>
             <?php
        $sql = "SELECT id, descripcion, obligatorio, partidas, clave "
        . " FROM catalogo_doctos "
        . " order by id ";
     
        $result = $db->openRS($sql);
        $count=0;
        while ($row = $db->fetchRow($result)) {
            
        ?>
            <tr class="inforow<?php echo $count % 2 ;?>">
                <td rowspan="<?php echo $row->partidas; ?>">
                    <label class="info"> <?php echo htmlentities($row->descripcion,ENT_SUBSTITUTE,"ISO-8859-1");?></label>
                </td>
        <?php for ($i=0; $i < $row->partidas; $i++) {
            if ($i>0) {
                echo "<tr class=\"inforow".  $count % 2 . "\">";
            }
        ?>
                <td style="border: none">
                    <label class="completo">
                        <?php if (trim($archivo[$row->id][$i]) != "") {
                            echo "El archivo " . $archivo[$row->id][$i] . " esta en el archivo";
                        }
                        ?></label>
                    <input type="hidden" name="archivo[]" value="<?php echo $archivo[$row->id][$i];?>">
                    <label class="required"><?php echo $errorMsg[$row->id][$i];?></label>
                </td>
                <td style="border-width: thin">
                    <input type="file" name="documentos[]" id="documentos[]">
                </td>
                <td>
                    <?php
                    if ($row->clave==2) {
                    ?>
                    <input type="text" name="clave[]" value="<?php echo $clave[$row->id][$i]; ?>" 
                                                                                class="textbox" size="15" maxlength="40">
                    <?php
                    } else {
                    ?>
                        <label class="label">&nbsp;</label>
                        <input type="hidden" name="clave[]" value="" class="textbox" size="15" maxlength="40">

                    <?php
                    }
                    ?>
                </td>
                <td>
                    <?php
                    if ($row->obligatorio==2) {
                    ?>
                        <label class="label">Este documento es requerido.</label>
                        <input type="hidden" name="razon[]" value="" class="textbox" size="15" maxlength="32">
                    <?php
                    } else {
                    ?>
                    <input type="text" name="razon[]" value="<?php echo $razon[$row->id][$i]; ?>" 
                                                                                class="textbox" size="15" maxlength="64">
                    <?php
                    }
                    ?>
                    
                </td>
            <?php
                    if ($i>0) {
                        echo "</tr>";
                    }

                } ?>
             </tr>
            <?php
            $count++;
        }
            ?>
            
        </table>
        </td>
    </tr>
    </table>
    <br>
<div style="text-align: center">
        <br>
         <p class="required">Solo dar UN Click, este proceso puede tardar varios minutos.</p> 
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <input class="continuar" type="submit" name="save" value="Guardar e ir al Panel">
    </div>
    </form>

</div>
</body>
</html>

<?php
$db=null;
?>