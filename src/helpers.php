<?php

function composer_helper($argv,$basepath)
{
    $paket = $argv[1];
    if(array_key_exists(2,$argv)) {
        $version = $argv[2];
    } else {
        $version = "dev-master";
    }

    $devonly = $argv[3];

    $run_composer_require = false;
    $git_remote_server = "";
    $composer_filepath = $basepath."/composer.json";
    if(file_exists($composer_filepath)) {
        if(file_exists($paket)) {
            $composer_vendor_path = $paket."/composer.json";
            if(file_exists($composer_vendor_path)) {
                $paket_name = composer_create_repositories($composer_vendor_path,$composer_filepath,false);
                $run_composer_require = true;
            } else {
                die("error vendor bukan paket composer\n\n");
            }
        } else {
            if(strpos($paket,":")) {
                $paket_array = explode(":",$paket);
                $git_remote_server = $paket_array[0];
                $paket_name = $paket_array[1];
                composer_create_repositories($paket_name,$composer_filepath,$git_remote_server);
            } else {
                die("error repository server not found\n\n");
            }
        }
    } else {
        die("file composer.json not found");
    }

    if($git_remote_server) {
        $command = "git ls-remote git@{$git_remote_server}:{$paket_name}.git";
        $hasil = run_proc($command);
        
        if(array_key_exists("output",$hasil) && preg_match("/(HEAD)/",$hasil['output'][0])) {
            $run_composer_require = true;
        } else {
            $user_home = $_SERVER['HOME'];
            $id_rsa_path = $user_home."/.ssh/id_rsa.pub";
            if(!file_exists($id_rsa_path)) {
                run_proc("ssh-keygen -t rsa -b 4096 -f {$user_home}/.ssh/id_rsa -q -N ''");
            }
            if($git_remote_server){
                run_proc("ssh-keyscan -H $git_remote_server >> {$user_home}/.ssh/known_hosts");
            }

            echo "\n\nanda perlu menyimpan ssh-key berikut di server $git_remote_server:\n\n";
            echo file_get_contents("{$user_home}/.ssh/id_rsa.pub")."\n\n";
        }
    }

    if($run_composer_require) {
        if($devonly == "dev") {
            $command = "composer require --dev {$paket_name}:{$version}";
        } else {
            $command = "composer require {$paket_name}:{$version}";
        }
        passthru($command);
    }
}

function create_resource($argv,$basepath)
{
    $nama_paket = $argv[2];
    // bikin folder packages
    // ignore folder tersebut
    // git init package
    // composer init package
    // git push origin
    // petruk paket --dev

}

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
        if(is_array($composer_vendor) && array_key_exists("name",$composer_vendor)) {
            $paket_name = $composer_vendor['name'];
            $return = $paket_name;
            if(check_create_repositories($paket_name,$composer_filepath)) {
                $paket_flat = get_paket_flat($paket_name);
                $paket_path = str_replace("composer.json","",$paket);
                $command = "composer config repositories.{$paket_flat} path $paket_path";
            }
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