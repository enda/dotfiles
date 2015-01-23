<?php
// First thing first, set encoding
$_ENV['ENCODING'] = 'UTF-8';
mb_internal_encoding($_ENV['ENCODING']);

// Set main path
$_ENV['ROOT_PATH']     = realpath(__DIR__ . '/..') . '/';
$_ENV['CONFIG_PATH']   = $_ENV['ROOT_PATH'] . 'config/';
$_ENV['ENGINE_PATH']   = $_ENV['ROOT_PATH'] . 'engine/';
$_ENV['SYSTEM_PATH']   = $_ENV['ENGINE_PATH'] . 'system/';

// Load the error_handler if needed
if (!function_exists('shape_errorHandler'))
    require($_ENV['SYSTEM_PATH'] . 'error_handler.php');

// Load all base conf
require($_ENV['CONFIG_PATH'] . 'env.php');
require($_ENV['CACHE_PATH'] . 'class_path.php');
require($_ENV['LOCALE_PATH'] . 'translation.url.php');

// Load shape system
require($_ENV['SYSTEM_PATH'] . "sys_engine.php");
require($_ENV['SYSTEM_PATH'] . 'sys_loader.php');
require($_ENV['SYSTEM_PATH'] . 'sys_error.php');
require($_ENV['SYSTEM_PATH'] . 'sys_model.php');
require($_ENV['SYSTEM_PATH'] . 'sys_lib.php');
require($_ENV['SYSTEM_PATH'] . 'sys_viewer.php');
require($_ENV['INCLUDE_PATH'] . 'fct_general.php');

// Load all conf
require($_ENV['CONFIG_PATH'] . 'static/conf_errno.php');
require($_ENV['CONFIG_PATH'] . 'static/conf_melty.php');
require($_ENV['CONFIG_PATH'] . 'static/conf_lib.php');
require($_ENV['CONFIG_PATH'] . 'static/data.php');
require($_ENV['CONFIG_PATH'] . 'static/wording.php');


if (isset($_SERVER['HTTP_REFERER']))
{
    $referrer = parse_url($_SERVER['HTTP_REFERER']);
    if (isset($referrer['host']) && isset($referrer['scheme']))
    {
        if (endWith($referrer['host'], $_ENV['DOMAIN_FULL']) && $referrer['host'] != $_SERVER['HTTP_HOST'])
            header("Access-Control-Allow-Origin: " . $referrer['scheme'] . "://" . $referrer['host']);
    }
}


try
{
    /* Initialisation of the config's path for the inheritance's
       tree of templates */
    $rewriting = new Melty_Rewriting($_ENV['REWRITES_PATH'],
                                     $_ENV['ROOT_PATH'] . 'www',
                                     $_GET,
                                     $_ENV);
    $response = $rewriting->rewrite($_SERVER['REQUEST_URI']);
    $_SERVER['DOCUMENT_URI'] = $rewriting->apply($response);

    if (isset($_SERVER["HTTP_CONTENT_TYPE"]) &&
        startWith($_SERVER["HTTP_CONTENT_TYPE"], "application/json"))
    {
        $_POST = json_decode(file_get_contents("php://input"), TRUE);
        if ($_POST === NULL) /* By default PHP never gives NULL in $_POST */
            $_POST = []; /* So let's not perturb this. */
    }

    // Launch Engine
    $engine = new melty_engine();
    $engine->run($_SERVER['DOCUMENT_URI'], $_GET, $_POST);
}
catch (Exception $ex)
{
    $error = new melty_error($ex);
    $error->dispatch_error();
    exit(0);
}

/*

  GG :-)
  -----BEGIN PUBLIC KEY-----
  MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAt/pZBKpSGjrMtTb0UHCI
  efuBymECvEsSd6cDnJZYP2tNi5uMkwJpgpmODjjSr7Q8x8A+5SN6mQ/FSmXjnvJp
  XfdZEUSHtn7ToVF1zP3Koa04UBQ1pWJhWmLHdmwX/bJV+y3GWOWkOSa3AjMLLQlJ
  lbkNEwj5bSIVCqii5udLvlQYMAkmXC59ADUCFP4SITlEmPM7vlRuiT+ZcLlKv82J
  tCwHHqrvtkfs+IAUgcMMLERX5ZLiEtRoE4324X/8vlrU5lhO12eF0dWKMQFM1pvs
  pWvBbwV0u4/uNrHP8ftFFpA/sQGZZwAHD/eihEu3XlrmU0ycKzjgQGnrsAL3PpNI
  eZz9EjIbTFQ2t6MOGYisLR3oiZYqspqZ2yNmvoCgldbxsXgDdbzbGXzU0QW7g9nI
  wlNoArp0zTr+U0Dn9TRNe/iNYbIvzNigcQYdCU+tKQtGTandAZNOEVsuzTP9lXEG
  PgdbdG4MqxLK2sS2ERSTarkHj3LS9acHJhcwwmya+/Xt4RsACS6VHjQrbSk5cPm4
  VLKZL+/eZysFWxS8zIuHuN6PwSNw/J1mainzBYysxbI24hTNAyTNmoa/YaoTOIah
  IPB6Y60rDH4bWNKLdjXdMdfoq4EtJ+az5bzaM/DRgSb2m0QyYZPslhkg6vJfy2kC
  6b5razLnjue8xm2KAOWYFYMCAwEAAQ==
  -----END PUBLIC KEY-----

*/
