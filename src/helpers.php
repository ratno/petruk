<?php
function composer_create_repositories($paket,$composer_filepath,$vcs="")
{
    $paket_flat = str_replace("/","_",$paket);

    $composer = json_decode(file_get_contents($composer_filepath),true);
    $create_new = true;
    if(array_key_exists("repositories",$composer)) {
        if(array_key_exists($paket_flat,$composer["repositories"])) {
            $create_new = false;
        }
    }

    if($create_new) {
        if($vcs) {
            passthru("composer config repositories.{$paket_flat} vcs git@{$vcs}:{$paket}.git");
        } else {
            passthru("composer config repositories.{$paket_flat} path $paket");
        }
    }
}