<?php
function composer_create_repositories($paket,$composer_filepath,$vcs="")
{

    $command = "";
    $return = $paket;

    if($vcs) {
        if(check_create_repositories($paket,$composer_filepath)) {
            $paket_flat = get_paket_flat($paket);
            $command = "composer config repositories.{$paket_flat} vcs git@{$vcs}:{$paket}.git";
        }
    } else {
        // $paket yg diinput disini adalah path menuju composer vendor
        $composer_vendor = json_decode(file_get_contents($paket),true);
        $paket_name = $composer_vendor['name'];
        $paket_flat = get_paket_flat($paket_name);
        $paket_path = str_replace("composer.json","",$paket);
        if(check_create_repositories($paket_name,$composer_filepath)) {
            $command = "composer config repositories.{$paket_flat} path $paket_path";
            $return = $paket_name;
        }
    }
    
    if($command) {
        passthru($command);
    }

    return $return;
}

function check_create_repositories($paket_name,$composer_filepath)
{
    $composer = json_decode(file_get_contents($composer_filepath),true);
    $create_new = true;
    if(array_key_exists("repositories",$composer)) {
        if(array_key_exists(get_paket_flat($paket_name),$composer["repositories"])) {
            $create_new = false;
        }
    }

    return $create_new;
}

function get_paket_flat($paket_name)
{
    return str_replace("/","_",$paket_name);
}

function run_proc($command)
{
    $env = NULL;
    $options = array('bypass_shell' => true);
    $cwd = NULL;
    $descriptorspec = [
        0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
        1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
        2 => array("pipe", "w")  // stderr is a file to write to
    ];

    $process = proc_open($command, $descriptorspec, $pipes, $cwd, $env, $options);

    $return = [];
    if (is_resource($process)) {
        while ($f = fgets($pipes[1])) {
            $return['output'][] = trim($f);
        }
        fclose($pipes[1]);
        while ($f = fgets($pipes[2])) {
            $return['error'][] = trim($f);
        }
        fclose($pipes[2]);

        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        $return['value'] = proc_close($process);   
    }
    return $return;
}