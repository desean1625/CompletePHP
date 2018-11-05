#!/usr/bin/env php
<?php
require __DIR__ . "/vendor/nikic/php-parser/lib/bootstrap.php";
$file = is_dir($argv[1]) ? $argv[1] : dirname($argv[1]);
if (!file_exists($file)) {
    exit();
}

$files = array();
while (strlen($file) > 1) {
    if (file_exists($file . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php")) {
        $autoloader = realpath($file) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";
        $autoload   = true;
        break;
    }
    $file = dirname($file);
}

$indexFile = $file . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "sublimeAutoComplete.json";
$parser    = new PhpParser\Parser(new PhpParser\Lexer);
if ($autoload) {
    chdir($file);
    if (file_exists($indexFile)) {
        echo file_get_contents($indexFile);
        return;
    }
    foreach (new AdvancedDirectoryIterator("-R *.php") as $i) {
        $path = $i->getPathname();
        if (stristr($path, DIRECTORY_SEPARATOR . "composer")) {
            continue;
        }

        $files[] = $path;
    }
}
$classes = array();
$out     = [];

foreach ($files as $key => $phpfile) {

    try {
        $stmts = $parser->parse(file_get_contents($file . DIRECTORY_SEPARATOR .$phpfile));
        // $stmts is an array of statement nodes
    } catch (PhpParser\Error $e) {
        echo 'Parse Error: ', $e->getMessage();
    }
    foreach ($stmts as $key => $value) {
        $namespace = "";
        if ($value instanceof PhpParser\Node\Stmt\Namespace_) {
            $namespace = implode("\\", $value->name->parts);
            foreach ($value->stmts as $key => $obj) {
                if ($obj instanceof PhpParser\Node\Stmt\Class_) {
                    $classes[] = $namespace . "\\" . $obj->name;
                }
            }
        }
    }

}

foreach ($classes as $key => $class) {
    try {
        $return = null;
        $cmd = "php " .escapeshellarg( __DIR__ . "/reflector.php") ." " .escapeshellarg($autoloader) . " " . escapeshellarg($class);
        exec($cmd, $return);
        $meths = json_decode(implode("\n", $return));
        if (!$meths) {
            echo "broke $class \n\n";
            echo implode("\n", $return)."\n\n";
            continue;
        }
        foreach ($meths as $meth => $info) {
            if(!isset($out[$meth])) $out[$meth] = array();
            $out[$meth] = array_merge($out[$meth], $info);
            # code...
        }

    } catch (Exception $e) {

    }
}
if(count($out) == 0) exit;
file_put_contents($indexFile, json_encode($out));
echo json_encode($out);

/**
 * Real Recursive Directory Iterator
 */
class RRDI extends RecursiveIteratorIterator
{
    /**
     * Creates Real Recursive Directory Iterator
     * @param string $path
     * @param int $flags
     * @return DirectoryIterator
     */
    public function __construct($path, $flags = 0)
    {
        parent::__construct(new RecursiveDirectoryIterator($path, $flags));
    }
}

/**
 * Real RecursiveDirectoryIterator Filtered Class
 * Returns only those items which filenames match given regex
 */
class AdvancedDirectoryIterator extends FilterIterator
{
    /**
     * Regex storage
     * @var string
     */
    private $regex;
    /**
     * Creates new AdvancedDirectoryIterator
     * @param string $path, prefix with '-R ' for recursive, postfix with /[wildcards] for matching
     * @param int $flags
     * @return DirectoryIterator
     */
    public function __construct($path, $flags = 0)
    {
        if (strpos($path, '-R ') === 0) {
            $recursive = true;
            $path      = substr($path, 3);}
        if (preg_match('~/?([^/]*\*[^/]*)$~', $path, $matches)) {
            // matched wildcards in filename
            $path        = substr($path, 0, -strlen($matches[1]) - 1); // strip wildcards part from path
            $this->regex = '~^' . str_replace('*', '.*', str_replace('.', '\.', $matches[1])) . '$~'; // convert wildcards to regex
            if (!$path) {
                $path = '.';
            }
            // if no path given, we assume CWD
        }
        parent::__construct($recursive ? new RRDI($path, $flags) : new DirectoryIterator($path));
    }
    /**
     * Checks for regex in current filename, or matches all if no regex specified
     * @return bool
     */
    public function accept()
    {
        // FilterIterator method
        return $this->regex === null ? true : preg_match($this->regex, $this->getInnerIterator()->getFilename());
    }
}
