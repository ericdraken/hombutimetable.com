<?php


// DISABLED
header('Content-type: application/json');
echo "{}";
exit();


////// Global Settings ///////////////////////////////
require_once(__DIR__ . '/includes/define_root.php');
require_once(__ROOT__.'/global_settings.php');
//////////////////////////////////////////////////////

// Defined in php.ini also
$timezone = "Asia/Tokyo";
if(function_exists('date_default_timezone_set')){ date_default_timezone_set($timezone); }

///////////////////////////////////

$updatesPath = "/updates/";
$aesSignatureKey = "xxxxxxxx";

///////////////////////////////////

$model = @$_GET["model"];

$update = array();
$update["filename"] = "HombuTeachers 20150804.update";
$update["date"] = "2015-08-04";
$update["url"] = "http://hombutimetable.com/api/updates/HombuTeachers%2020150804.update";

// Calculate the compressed update signature
// base64(AES(strtolower(CRC(HombuUupdate 2015-08-04.update) + url)))
$fullUpdatesPath = dirname(__FILE__) . $updatesPath;
$compressedCRC = hash_file('crc32b', $fullUpdatesPath . $update["filename"]);

//echo "COMPRESSED CRC: " . $compressedCRC;

$plainSignature = strtolower($compressedCRC . $update["url"]);
$encryptedSignatureString = encryptAES128WithPKCS7($plainSignature, $aesSignatureKey);

//echo "DATA: " . $encryptedSignatureString;

$update["sign"] = base64_encode($encryptedSignatureString);

//die( $plainSignature );

// Calculate the CRC of the expanded update file
$sourceCRC = hash_file('crc32b', $fullUpdatesPath . "HombuTeachers 20150804.sqldata");
$update["crc"] = $sourceCRC;

header('Content-type: application/json');

echo json_encode($update);



//////////////////

// Helper functions

// This adds PKCS7 padding to the cyphertext
// REF: https://paragonie.com/blog/2015/05/if-you-re-typing-word-mcrypt-into-your-code-you-re-doing-it-wrong
function encryptAES128WithPKCS7($message, $key)
{
    $block = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'ecb') ?: 16;

    if (mb_strlen($key, '8bit') !== $block) {
        throw new Exception("Needs a 128-bit key!");
    }

    // Add PKCS7 Padding
    $pad_count = $block - (strlen($message) % $block);
    $message .= str_repeat(chr($pad_count), $pad_count);

    $ciphertext = mcrypt_encrypt(
        MCRYPT_RIJNDAEL_128,
        $key,
        $message,
        MCRYPT_MODE_ECB
    );

    return $ciphertext;
}
