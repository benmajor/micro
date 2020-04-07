<?php

$serverChecks = [
    [
        'text' => 'PHP version >= <code>'.MICRO_MIN_PHP_VERSION.'</code>; currently running <code><b>'.phpversion().'</b></code>.',
        'test' => micro_min_php_verion()
    ],
    [
        'text' => 'PHP extension <code>json</code> enabled.',
        'test' => micro_json_enabled()
    ],
    [
        'text' => 'PHP extension <code>mbstring</code> enabled.',
        'test' => micro_mbstring_enabled()
    ]
];

$tests = [
    [
        'text' => 'Content directory (<code>'.basename(MICRO_DIR_CONTENT).'</code>) exists',
        'test' => micro_content_directory_exists()
    ],
    [
        'text' => 'Content directory (<code>'.basename(MICRO_DIR_CONTENT).'</code>) is writeable.',
        'test' => micro_content_directory_is_writable()
    ],
    [
        'text' => '<code>/app/etc/</code> directory exists.',
        'test' => micro_etc_directory_exists()
    ],
    [
        'text' => '<code>/app/etc/</code> directory is writeable.',
        'test' => micro_etc_directory_is_writable()
    ],
    [
        'text' => 'At least one theme is installed.',
        'test' => micro_theme_installed()
    ]
];

$installFlag = (array_sum(array_column($tests, 'test')) == count(array_column($tests, 'test'))) && ((array_sum(array_column($serverChecks, 'test')) == count(array_column($serverChecks, 'test'))));

?><!doctype html>
<html lang="">
    <head>
        <meta charset="utf-8" />
        <title>Micro Installer</title>
        
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
        <meta name="theme-color" content="#000000" />
        
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Passion+One&display=swap" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/4.5.6/css/ionicons.min.css" />
        <link rel="stylesheet" href="views/css/installer.css" />
    </head>
    
    <body class="micro-installer micro-installer-installed">
        
        <div class="installer-container">
            <div class="micro-brand">
                <span class="icon">μ</span>
            </div>
            
            <div class="installer-content">
                <p>
                    Thanks for choosing Micro as your new content management system &ndash; we&rsquo;re sure you won&rsquo;t be disappointed! Before you can dive right in and start writing awesome content, we
                    just need to get a few things set up to make sure things run smoothly.
                </p>
                
                <h3>Environment checks:</h3>
                
                <ul class="checklist plain">
                    <?php foreach( $serverChecks as $test ) : ?>
                    <li>
                        <i class="<?= ($test['test']) ? 'ion-md-checkmark good' : 'ion-md-close bad' ?>"></i>
                        <?= $test['text'] ?>
                    </li>
                    <?php endforeach ?>
                </ul>
                
                <hr />
                
                <h3>Preflight checks:</h3>
                
                <ul class="checklist plain">
                    <?php foreach( $tests as $test ) : ?>
                    <li>
                        <i class="<?= ($test['test']) ? 'ion-md-checkmark good' : 'ion-md-close bad' ?>"></i>
                        <?= $test['text'] ?>
                    </li>
                    <?php endforeach ?>
                </ul>
                
                <?php if( $installFlag ) : ?>
                <p>
                    Looks like we&rsquo;re all set&hellip;
                </p>
                
                <form method="post">
                    <button class="btn" type="submit" name="goto-2">Let&rsquo;s go!</button>
                </form>
                
                <?php else : ?>
                <p>
                    <b>Please fix any items marked with a red cross above before continuing the installation.</b>
                </p>
                <?php endif ?>
            </div>
        </div>
        
    </body>
</html>