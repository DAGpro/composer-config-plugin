<?php

namespace Yiisoft\Composer\Config;

use Composer\Composer;
use Composer\Package\CompletePackageInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\Filesystem;

/**
 * Class Package.
 */
class Package
{
    public const EXTRA_FILES_OPTION_NAME           = 'config-plugin';
    public const EXTRA_DEV_FILES_OPTION_NAME       = 'config-plugin-dev';
    public const EXTRA_OUTPUT_DIR_OPTION_NAME      = 'config-plugin-output-dir';
    public const EXTRA_ALTERNATIVES_OPTION_NAME    = 'config-plugin-alternatives';

    private $options = ['output-dir', 'alternatives'];

    protected $package;

    /**
     * @var array composer.json raw data array
     */
    protected $data;

    /**
     * @var string absolute path to the root base directory
     */
    protected $baseDir;

    /**
     * @var string absolute path to vendor directory
     */
    protected $vendorDir;

    /**
     * @var Filesystem utility
     */
    protected $filesystem;

    private $composer;

    public function __construct(PackageInterface $package, Composer $composer)
    {
        $this->package = $package;
        $this->composer = $composer;
    }

    /**
     * Collects package aliases.
     * @return array collected aliases
     */
    public function collectAliases(): array
    {
        $aliases = array_merge(
            $this->prepareAliases('psr-0'),
            $this->prepareAliases('psr-4')
        );
        if ($this->isRoot()) {
            $aliases = array_merge(
                $aliases,
                $this->prepareAliases('psr-0', true),
                $this->prepareAliases('psr-4', true)
            );
        }

        return $aliases;
    }

    /**
     * Prepare aliases.
     * @param string 'psr-0' or 'psr-4'
     * @param bool $dev
     * @return array
     */
    protected function prepareAliases(string $psr, bool $dev = false): array
    {
        $autoload = $dev ? $this->getDevAutoload() : $this->getAutoload();
        if (empty($autoload[$psr])) {
            return [];
        }

        $aliases = [];
        foreach ($autoload[$psr] as $name => $path) {
            if (is_array($path)) {
                // ignore psr-4 autoload specifications with multiple search paths
                // we can not convert them into aliases as they are ambiguous
                continue;
            }
            $name = str_replace('\\', '/', trim($name, '\\'));
            $path = $this->preparePath($path);
            if ('psr-0' === $psr) {
                $path .= '/' . $name;
            }
            $aliases["@$name"] = $path;
        }

        return $aliases;
    }

    /**
     * @return string package pretty name, like: vendor/name
     */
    public function getPrettyName(): string
    {
        return $this->package->getPrettyName();
    }

    /**
     * @return string package version, like: 3.0.16.0, 9999999-dev
     */
    public function getVersion(): string
    {
        return $this->package->getVersion();
    }

    /**
     * @return string package human friendly version, like: 5.x-dev d9aed42, 2.1.1, dev-master f6561bf
     */
    public function getFullPrettyVersion(): string
    {
        return $this->package->getFullPrettyVersion();
    }

    /**
     * @return string package CVS revision, like: 3a4654ac9655f32888efc82fb7edf0da517d8995
     */
    public function getSourceReference(): ?string
    {
        return $this->package->getSourceReference();
    }

    /**
     * @return string package dist revision, like: 3a4654ac9655f32888efc82fb7edf0da517d8995
     */
    public function getDistReference(): ?string
    {
        return $this->package->getDistReference();
    }

    /**
     * @return bool is package complete
     */
    public function isComplete(): bool
    {
        return $this->package instanceof CompletePackageInterface;
    }

    /**
     * @return bool is this a root package
     */
    public function isRoot(): bool
    {
        return $this->package instanceof RootPackageInterface;
    }

    /**
     * @return string package type, like: package, library
     */
    public function getType(): string
    {
        return $this->getRawValue('type') ?? $this->package->getType();
    }

    /**
     * @return array autoload configuration array
     */
    public function getAutoload(): array
    {
        return $this->getRawValue('autoload') ?? $this->package->getAutoload();
    }

    /**
     * @return array autoload-dev configuration array
     */
    public function getDevAutoload(): array
    {
        return $this->getRawValue('autoload-dev') ?? $this->package->getDevAutoload();
    }

    /**
     * @return array requre configuration array
     */
    public function getRequires(): array
    {
        return $this->getRawValue('require') ?? $this->package->getRequires();
    }

    /**
     * @return array requre-dev configuration array
     */
    public function getDevRequires(): array
    {
        return $this->getRawValue('require-dev') ?? $this->package->getDevRequires();
    }

    /**
     * @return array files array
     */
    public function getFiles(): array
    {
        return $this->getExtraValue(self::EXTRA_FILES_OPTION_NAME, []);
    }

    /**
     * @return array dev-files array
     */
    public function getDevFiles(): array
    {
        return $this->getExtraValue(self::EXTRA_DEV_FILES_OPTION_NAME, []);
    }

    /**
     * @return array output-dir option
     */
    public function getOutputDir(): ?string
    {
        return $this->getExtraValue(self::EXTRA_OUTPUT_DIR_OPTION_NAME);
    }

    /**
     * @return mixed alternatives array or path to config
     */
    public function getAlternatives()
    {
        return $this->getExtraValue(self::EXTRA_ALTERNATIVES_OPTION_NAME);
    }

    /**
     * @return array alternatives array
     */
    public function getExtraValue($key, $default = null)
    {
        return $this->getExtra()[$key] ?? $default;
    }

    /**
     * @return array extra configuration array
     */
    public function getExtra(): array
    {
        return $this->getRawValue('extra') ?? $this->package->getExtra();
    }

    /**
     * @param string $name option name
     * @return mixed raw value from composer.json if available
     */
    public function getRawValue(string $name)
    {
        if ($this->data === null) {
            $this->data = $this->readRawData();
        }

        return $this->data[$name] ?? null;
    }

    /**
     * @return mixed all raw data from composer.json if available
     */
    public function getRawData(): array
    {
        if ($this->data === null) {
            $this->data = $this->readRawData();
        }

        return $this->data;
    }

    /**
     * @return array composer.json contents as array
     */
    protected function readRawData(): array
    {
        $path = $this->preparePath('composer.json');
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        return [];
    }

    /**
     * Builds path inside of a package.
     * @param string $file
     * @return string absolute paths will stay untouched
     */
    public function preparePath(string $file): string
    {
        if (0 === strncmp($file, '$', 1)) {
            return $file;
        }

        $skippable = 0 === strncmp($file, '?', 1) ? '?' : '';
        if ($skippable) {
            $file = substr($file, 1);
        }

        if (!$this->getFilesystem()->isAbsolutePath($file)) {
            $prefix = $this->isRoot()
                ? $this->getBaseDir()
                : $this->getVendorDir() . '/' . $this->getPrettyName();
            $file = $prefix . '/' . $file;
        }

        return $skippable . $this->getFilesystem()->normalizePath($file);
    }

    /**
     * Get absolute path to package base dir.
     * @return string
     */
    public function getBaseDir(): string
    {
        if (null === $this->baseDir) {
            $this->baseDir = dirname($this->getVendorDir());
        }

        return $this->baseDir;
    }

    /**
     * Get absolute path to composer vendor dir.
     * @return string
     */
    public function getVendorDir(): string
    {
        if (null === $this->vendorDir) {
            $dir = $this->composer->getConfig()->get('vendor-dir');
            $this->vendorDir = $this->getFilesystem()->normalizePath($dir);
        }

        return $this->vendorDir;
    }

    /**
     * Getter for filesystem utility.
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        if (null === $this->filesystem) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }
}
