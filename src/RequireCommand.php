<?php
namespace Ratno\Petruk\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class RequireCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('require')
            ->setDescription('Composer require khusus untuk private repo')
            ->addArgument('nama_paket_folder', InputArgument::REQUIRED, 'Nama paket (git.server-repo.com:nama/paket atau https://git.server-repo.com/nama/paket) atau nama folder')
            ->addArgument('versi', InputArgument::OPTIONAL, 'Versi paket (jika tidak diisi maka akan diambilkan ke dev-master)')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Menginstall paket untuk require-dev')
            ->addOption('global', null, InputOption::VALUE_NONE, 'Menginstall paket dengan composer global')
            ->addOption('paket', "p", InputOption::VALUE_REQUIRED, 'Override setting nama paket');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nama_paket_folder = $input->getArgument('nama_paket_folder');
        $versi = $input->getArgument("versi") ?: "dev-master";
        $dev = $input->getOption("dev");
        $global = $input->getOption("global");
        $custom_paket = $input->getOption("paket");

        if($global) {
            $composer = "composer global";
        } else {
            $composer = "composer";
        }

        $this->createComposerJsonFileIfNotExists();

        $nama_paket = $this->checkIfNamaPaketFolderIsLocalFolder($nama_paket_folder);
        $remote_uri = "";
        $scheme_type = "";
        $uri_full_path = "";
        if($nama_paket) {
            $type = "path";
            $folder = $nama_paket_folder;
        } elseif(preg_match("/^(http)/",$nama_paket_folder)) {
            $http_git = parse_url($nama_paket_folder);
            $type = "vcs";
            $remote_uri = $http_git['host'];
            $nama_paket = preg_replace("/^\//","",$http_git['path']);
            $scheme_type = "http";
            $uri_full_path = $nama_paket_folder . (preg_match("/(.git)$/",$nama_paket_folder)?"":".git");
        } else {
            $type = "vcs";
            $scheme_type = "ssh";
            $remote_paket = explode(":",$nama_paket_folder);
            if(is_array($remote_paket) && count($remote_paket) == 2) {
                $nama_paket = $remote_paket[1];
                $remote_uri = $remote_paket[0];
                $uri_full_path = "git@{$remote_uri}:{$nama_paket}.git";
            } else {
                $output->writeln('<error>Nama Remote Repo harus dalam format:</error> <info>git.server-repo.com:nama/paket</info>');
                return 0;
            }
        }

        if($custom_paket) {
            $nama_paket = $custom_paket;
        }

        $paket_flat = str_replace("/","_",$nama_paket);

        if($type == "path") {
            $command_config = "$composer config repositories.{$paket_flat} path $folder";
        } elseif ($type == "vcs") {
            $command_config = "$composer config repositories.{$paket_flat} vcs {$uri_full_path}";
        }

//        $output->writeln("<info>$command_config</info>");

        if($remote_uri) {
            $command = "git ls-remote {$uri_full_path}";
            $hasil = run_proc($command);

            if(array_key_exists("output",$hasil) && preg_match("/(HEAD)/",$hasil['output'][0])) {
                // do nothin to allow run composer require
            } else {
                if($scheme_type == "ssh") {
                    $user_home = $_SERVER['HOME'];
                    $id_rsa_path = $user_home."/.ssh/id_rsa.pub";
                    if(!file_exists($id_rsa_path)) {
                        run_proc("ssh-keygen -t rsa -b 4096 -f {$user_home}/.ssh/id_rsa -q -N ''");
                    }
                    if($remote_uri){
                        run_proc("ssh-keyscan -H $remote_uri >> {$user_home}/.ssh/known_hosts");
                    }

                    $output->writeln("<error>Tidak dapat mengakses server $remote_uri, pastikan alamat server benar.</error>");
                    $output->writeln("<error>Pastikan anda telah menyimpan ssh-key berikut di server $remote_uri:</error>");
                    $output->writeln("<fg=black;bg=green>".trim(file_get_contents("{$user_home}/.ssh/id_rsa.pub"))."</>");
                    $output->writeln("");
                    $output->writeln("<info>Setelah menyimpan ssh-key silahkan ulangi perintah anda tadi.</info>");
                    return 0;
                }
            }
        }

        // sampai sini berarti telah sukses melewati semua pre-requisite-nya
        $command_require = "$composer require {$nama_paket}:{$versi}";
        if($dev) {
            $command_require .= " --dev";
        }

//        $output->writeln("<info>$command_require</info>");

        $commands = [
            $command_config,
            $command_require
        ];

        $process = new Process($commands, getcwd(), null, null, null);
        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }
        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });
        $output->writeln('<comment>Happy Coding -Ratno-</comment>');
        
        return 0;
    }

    protected function createComposerJsonFileIfNotExists()
    {
        $composer_json_file = getcwd()."/composer.json";
        if(!file_exists($composer_json_file)) {
            $filesystem = new Filesystem;
            $filesystem->dumpFile($composer_json_file,"{}");
        }
    }

    protected function checkIfNamaPaketFolderIsLocalFolder($nama_paket_folder)
    {
        if(file_exists($nama_paket_folder)) {
            // check apakah folder tersebut memiliki composer.json
            $vendor_composer_file = $nama_paket_folder."/composer.json";
            if(file_exists($vendor_composer_file)){
                $composer_vendor = json_decode(file_get_contents($vendor_composer_file),true);
                if(is_array($composer_vendor) && array_key_exists("name",$composer_vendor)) {
                    return $composer_vendor['name'];
                }
            }
        }

        return false;
    }
}