<?php

//TODO 
// - force the user to come from an authenticated page
// - htaccess force file type
//
function dbg($msg,$die){
    $DEBUG=1;
    if ($DEBUG) {
        echo $msg."<br/>";
        if ($die==1) {
            echo "<a href='./acq.php'>back</a><br/>";
            die ();
        }
    }
}

function truePDF(){
    // verify the file is a PDF
    $mime = "application/pdf; charset=binary";
    $cmd="file -bi ".$_FILES['userfile']['tmp_name']." 2>&1";
    dbg( "cmd=$cmd",0);
    exec($cmd, $out,$ret);
    if ($out[0] != $mime) {
        // file is not a PDF
        dbg ("file is not pdf",0);
    } else {
        dbg ("file is pdf",0);
        return 1;
    }
    return 0;
}

function goodSize() {
    // check file size reported & stored match AND doesn't excede
    // max_upload_size
    $max_file_size = 8388608; // 8mb
    dbg ("uploadfile size: ".$_FILES['userfile']['size'],0);
    if ($_FILES['userfile']['size'] < $max_file_size) {
        dbg ("actual file size: " . filesize($_FILES['userfile']['tmp_name']),0);
        if ($_FILES['userfile']['size'] === filesize($_FILES['userfile']['tmp_name'])) {
            dbg ("file size good",0);
            return 1;
        }
    } else {
        dbg ("file size excedes limit",0);
    }
    return 0;
}

function dbinsert($table,$arr_cols,$arr_values) {

    $username="maria";
    $password = "securepw";
    $host = "localhost";
    $dbname = "messin";

    $options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');
    try
    {
        $db = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8", $username, $password, $options);
    }
    catch(PDOException $ex)
    {
        dbg("Failed to connect to the database: " . $ex->getMessage(),0);
        return 0; //failure
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $query = "
        INSERT INTO repres (
            filename,
            filepath
        ) VALUES (
            :filename,
            :filepath
        )
        ";

    $query_params = array(
        ':filename' => $arr_values[0],
        ':filepath' => $arr_values[1]
    );

    try
    {
        // Execute the query to create the user
        $stmt = $db->prepare($query);
        $result = $stmt->execute($query_params);
    }
    catch(PDOException $ex)
    {
        // Note: On a production website, you should not output $ex->getMessage().
        // It may provide an attacker with helpful information about your code. 
        dbg("Failed to run query: " . $ex->getMessage(),0);
        return 0; //failure
    }

    return 1; // success
}

function mapUpload($store_filepath,$lookup_db_filename) {
    $table = "repres";
    $arr_cols = array ( "filename", "filepath");
    $arr_values = array ($lookup_db_filename, $store_filepath);

    dbinsert($table,$arr_cols,$arr_values);
    return 1; 
    return 0; // failure
}

// main
define ("UPLOAD_DIR","/home/hchu/");

if (!empty($_POST)) {
    if (isset($_POST['upload'])) {

        // check $_FILES['userfile']['error']
        print_r($_FILES);
        if ($_FILES['userfile']['error'] > 0) {
            dbg ("error 1",1);
        } 

        // extensions are meaningless
        if (!truePDF()) {
            dbg ("unrecognized file",1);
        }

        // clean user input
        $lookup_db_filename = preg_replace("/[^A-Z0-9._-]/i", "_",$_FILES['userfile']['name']);
        dbg ("lookup_db_filename=$lookup_db_filename",0);

        // size 
        if (!goodSize()) {
            dbg ("size no good" , 1) ;
        }

        // generate random name 
        $store_filename = md5(uniqid("rprt_",true)).".pdf";
        $store_filepath = UPLOAD_DIR . $store_filename;
        dbg ("store_filename: $store_filename | store_filepath: $store_filepath",0);

        // store out of document root
        $success = move_uploaded_file($_FILES['userfile']['tmp_name'],$store_filepath);
        if (!$success) {
            dbg ("unable to save file",1);
        } else {
            dbg ("success",0);
            // map store_filname to lookup table
            if (mapUpload($store_filepath,$lookup_db_filename)) {
                header("Location: ./reports.php");
            }
            // todo if map is not successful, delete upload, abort..
        }

        // chmod the file
        chmod($store_filepath, 0644);
        // limit the number of file uploads (?)
        //
    } elseif (isset($_POST['cancel'])){
        dbg( "canceling...",0);
    }
}
?>
<form action="acq.php" method="post" enctype="multipart/form-data">
    <input type="file" name="userfile" class="btn btn-small btn-primary">
    <input type="submit" value="Upload" name="upload" class="btn btn-small btn-primary">
    <input type="submit" value="Cancel" name="cancel" class="btn btn-small btn-primary">
</form>
