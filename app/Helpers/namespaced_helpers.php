<?php

namespace App\Helpers;

use App\Library\StringHelper;
use Artisan;
use Exception;
use File;
use SimpleXMLElement;

/**
 * @throws Exception
 */
function generatePublicPath($absPath, $withHost = false)
{
    if (empty(trim($absPath))) {
        throw new Exception('Empty path');
    }

    $excludeBase = storage_path();
    $pos = strpos($absPath, $excludeBase); // Expect pos to be exactly 0

    if ($pos === false) {
        throw new Exception(sprintf("File '%s' cannot be made public, only files under storage/ folder can", $absPath));
    }

    if ($pos != 0) {
        throw new Exception(sprintf("Invalid path '%s', cannot make it public", $absPath));
    }

    $relativePath = substr($absPath, strlen($excludeBase) + 1);

    $dirname = dirname($relativePath);
    $basename = basename($relativePath);
    $encodedDirname = StringHelper::base64UrlEncode($dirname);

    // If Laravel is under a subdirectory
    $subdirectory = getAppSubdirectory();

    if (empty($subdirectory) || $withHost) {
        $url = route('public_assets', [ 'dirname' => $encodedDirname, 'basename' => rawurlencode($basename) ], $withHost);
    } else {
        // Make sure the $subdirectory has a leading slash ('/')
        $subdirectory = join_paths('/', $subdirectory);
        $url = join_paths($subdirectory, route('public_assets', [ 'dirname' => $encodedDirname, 'basename' => $basename ], $withHost));
    }

    return $url;
}

function getAppSubdirectory()
{
    $path = parse_url(config('app.url'), PHP_URL_PATH);

    if (is_null($path)) {
        return null;
    }

    $path = trim($path, '/');
    return empty($path) ? null : $path;
}

// Get application host with {scheme}://{host}:{port} (without subdirectory)
/**
 * @throws Exception
 */
function getAppHost()
{
    $fullUrl = config('app.url');
    $meta = parse_url($fullUrl);

    if (!array_key_exists('scheme', $meta) || !array_key_exists('host', $meta)) {
        throw new Exception('Invalid app.url setting');
    }

    $appHost = "{$meta['scheme']}://{$meta['host']}";

    if (array_key_exists('port', $meta)) {
        $appHost = "{$appHost}:{$meta['port']}";
    }

    return $appHost;
}

function ptouch($filepath)
{
    $dirname = dirname($filepath);
    if (!File::exists($dirname)) {
        File::makeDirectory($dirname, 0777, true, true);
    }

    touch($filepath);
}

function xml_to_array(SimpleXMLElement $xml)
{
    $parser = function (SimpleXMLElement $xml, array $collection = []) use (&$parser) {
        $nodes = $xml->children();
        $attributes = $xml->attributes();

        if (0 !== count($attributes)) {
            foreach ($attributes as $attrName => $attrValue) {
                $collection['attributes'][$attrName] = html_entity_decode(strval($attrValue));
            }
        }

        if (0 === $nodes->count()) {
            // $collection['value'] = stream($xml);
            // return $collection;
            return html_entity_decode(strval($xml));
        }

        foreach ($nodes as $nodeName => $nodeValue) {
            if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
                $collection[$nodeName] = $parser($nodeValue);
                continue;
            }

            $collection[$nodeName][] = $parser($nodeValue);
        }

        return $collection;
    };

    return [
            $xml->getName() => $parser($xml)
    ];
}



function write_env($key, $value, $overwrite = true)
{
    // Important, make the new environment var available
    // Otherwise, this method may failed if called twice (in a loop for example) in the same process
    Artisan::call('config:clear');

    // In case config:clear does not work
    if (file_exists(base_path('bootstrap/cache/config.php'))) {
        unlink(base_path('bootstrap/cache/config.php'));
    }

    $envs = load_env_from_file(app()->environmentFilePath());

    // Set the value if overwrite is set to true or the key value is empty
    if ($overwrite || !array_key_exists($key, $envs) || empty($envs[$key])) {
        // Quote if there is at least one space or # or any suspected char!
        if (preg_match('/[\s#!$]/', $value)) {
            // Escape single quote
            $value = addcslashes($value, '"');
            $value = "\"$value\"";
        }

        $envs[$key] = $value;
    } else {
        return;
    }

    $out = [];
    foreach ($envs as $k => $v) {
        $out[] = "$k=$v";
    }

    $out = implode("\n", $out);

    // Actually write to file .env
    file_put_contents(app()->environmentFilePath(), $out);
}

function write_envs($params)
{
    foreach ($params as $key => $value) {
        write_env($key, $value);
    }
}

function reset_app_url($force = false)
{
    $envs = load_env_from_file(app()->environmentFilePath());
    if (!array_key_exists('APP_URL', $envs) || $force) {
        $url = url('/');
        write_env('APP_URL', $url);
    }
}


// IMPORTANT
// + This function does not purify values, it will load raw content like: [ DB => "'mydb'", OTHER => '""']
// + Allow only a-zA-Z_ in key name
function load_env_from_file($path)
{
    $content = file_get_contents($path);
    $lines = preg_split("/(\r\n|\n|\r)/", $content);
    $lines = array_where($lines, function ($value) {
        if (is_null($value)) {
            return false;
        }

        if (preg_match('/^[a-zA-Z0-9_]+=/', $value)) {
            return true;
        } else {
            return false;
        }
    });

    $output = [];
    foreach ($lines as $line) {
        [$key, $value] = explode('=', $line, 2);

        if (is_null($value)) {
            $value = '';
        } else {
            $value = trim($value);
        }

        $output[ $key ] = $value;
    }

    return $output;
}

// Copy and:
// + Remove the destination first
// + Create parent folders if not exist
/**
 * @throws Exception
 */
function pcopy($src, $dst): void
{
    if (!File::exists($src)) {
        throw new Exception("File `{$src}` does not exist");
    }

    if (File::exists($dst)) {
        // Delete the file or link or directory
        if (is_link($dst) || is_file($dst)) {
            File::delete($dst);
        } else {
            File::deleteDirectory($dst);
        }
    } else {
        // Make sure the PARENT directory exists
        $dirname = pathinfo($dst)['dirname'];
        if (!File::exists($dirname)) {
            File::makeDirectory($dirname, 0777, true, true);
        }
    }

    // if source is a file, just copy it
    if (File::isFile($src)) {
        File::copy($src, $dst);
    } else {
        File::copyDirectory($src, $dst);
    }
}
