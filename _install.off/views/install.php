<?php

global $tpl;

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
            
            <form method="post" class="installer-content">
                
                <p>
                    You&rsquo;re just one step away from Micro heaven! Just fill in the few fields below and you&rsquo;ll be all set to get started. If you don&rsquo;t
                    have all of the information to hand now, don&rsquo;t worry &ndash; you can always add them later by modifying the <code>config.yaml</code> file.
                </p>
                
                <?php if( isset($tpl['alert']) ) : ?>
                <div class="alert alert-<?php echo $tpl['alert']['cls'] ?>">
                    <?php echo $tpl['alert']['txt'] ?>
                </div>
                <?php endif ?>
                
                <hr />
                
                <div class="install-field">
                    <div class="field-label">
                        <label for="input-site-name">Site name:</label>
                    </div>
                    <div class="field-input">
                        <input type="text" name="site-name" id="input-site-name" placeholder="My Awesome Website!" autofocus required />
                        <p class="help-text">This is the name of the website as it appears in the meta title and header (of some themes).</p>
                    </div>
                </div>
                
                <div class="install-field">
                    <div class="field-label">
                        <label for="input-site-domain">Site domain:</label>
                    </div>
                    <div class="field-input">
                        <input type="text" name="site-domain" id="input-site-domain" placeholder="http://www.example.com" value="<?php echo micro_get_domain() ?>" required />
                        <p class="help-text">The primary domain (including protocol) for your awesome website.</p>
                    </div>
                </div>
                
                <div class="install-field">
                    <div class="field-label">
                        <label for="input-site-directory">Site directory:</label>
                    </div>
                    <div class="field-input">
                        <input type="text" name="site-directory" id="input-site-directory" placeholder="/" value="<?php echo micro_get_directory() ?>" required />
                        <p class="help-text">If you&rsquo;re installing Micro in your web root, enter <code>/</code>; otherwise use the full installation directory, including the trailing <code>/</code>.</p>
                    </div>
                </div>
                
                <div class="install-field">
                    <div class="field-label">
                        <label for="select-theme">Theme:</label>
                    </div>
                    <div class="field-input">
                        <?php $themes = micro_get_themes() ?>
                        <select id="select-theme" name="site-theme"<?php if(count($themes) == 1) { echo ' readonly'; }?>>
                            <?php foreach( $themes as $theme ) : ?>
                            <option value="<?php echo $theme ?>"><?php echo $theme ?></option>
                            <?php endforeach ?>
                        </select>
                        <p class="help-text">
                            <?php if( count($themes) == 1 ) : ?>
                            Only one theme was detected, so we have pre-selected it. Themes can always be installed later.
                            <?php else : ?>
                            Select the theme that you would like to use with this installation.
                            <?php endif ?>
                        </p>
                    </div>
                </div>
                
                <button class="btn" type="submit" name="install">Install</button>
            </form>
        </div>
        
    </body>
</html>