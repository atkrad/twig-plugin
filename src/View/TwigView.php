<?php

namespace Twig\View;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\View\Exception\MissingViewException;
use Cake\View\View;
use Twig\Extension\Twig_Extension_Basic;
use Twig\Extension\Twig_Extension_I18n;
use Twig\Lib\StaticCaller;
use Twig_Environment;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;

/**
 * Class TwigView
 *
 * @package App\View
 */
class TwigView extends View
{
    /**
     * File extension.
     *
     * @var string
     */
    protected $_ext = '.twig';

    /**
     * Stores the Twig configuration.
     *
     * @var Twig_Environment
     */
    protected $twig;

    protected $paths = [];

    /**
     * Constructor
     *
     * @param \Cake\Network\Request    $request      Request instance.
     * @param \Cake\Network\Response   $response     Response instance.
     * @param \Cake\Event\EventManager $eventManager Event manager instance.
     * @param array                    $viewOptions  View options. See View::$_passedVars for list of
     *                                               options which get set as class properties.
     */
    public function __construct(
        Request $request = null,
        Response $response = null,
        EventManager $eventManager = null,
        array $viewOptions = []
    )
    {
        parent::__construct($request, $response, $eventManager, $viewOptions);

        $this->twig = new Twig_Environment($this->getFilesystemLoader(), [
            'debug' => Configure::read('debug'),
            'autoescape' => false
        ]);

        $this->addGlobals();
        $this->addExtensions();

        if (Configure::read('debug')) {
            $this->twig->addExtension(new Twig_Extension_Debug());
        } else {
            $this->twig->setCache(TMP . 'twig');
        }
    }

    /**
     * Get Twig
     *
     * @return Twig_Environment
     */
    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * Renders view for given view file and layout.
     *
     * Render triggers helper callbacks, which are fired before and after the view are rendered,
     * as well as before and after the layout. The helper callbacks are called:
     *
     * - `beforeRender`
     * - `afterRender`
     *
     * If View::$autoRender is false and no `$layout` is provided, the view will be returned bare.
     *
     * View and layout names can point to plugin views/layouts. Using the `Plugin.view` syntax
     * a plugin view/layout can be used instead of the app ones. If the chosen plugin is not found
     * the view will be located along the regular view path cascade.
     *
     * @param string $view   Name of view file to use
     * @param string $layout Layout to use.
     *
     * @return string|null Rendered content or null if content already rendered and returned earlier.
     * @throws \Cake\Error\Exception If there is an error in the view.
     */
    public function render($view = null, $layout = null)
    {
        if ($this->hasRendered) {
            return;
        }

        $renderedTemplate = '';
        if ($view !== false && $viewFileName = $this->getViewFileName($view)) {
            $this->_currentType = static::TYPE_VIEW;
            $this->eventManager()->dispatch(new Event('View.beforeRender', $this, [$viewFileName]));
            $renderedTemplate = $this->_render($viewFileName);
            $this->eventManager()->dispatch(new Event('View.afterRender', $this, [$viewFileName]));
        }

        $this->hasRendered = true;
        return $renderedTemplate;
    }

    /**
     * Renders and returns output for given view filename with its
     * array of data. Handles parent/extended views.
     *
     * @param string $viewFile Filename of the view
     * @param array  $data     Data to include in rendered view. If empty the current View::$viewVars will be used.
     *
     * @throws Exception
     * @return string Rendered output
     */
    protected function _render($viewFile, $data = array())
    {
        if (empty($data)) {
            $data = $this->viewVars;
        }
        $this->_current = $viewFile;

        $eventManager = $this->eventManager();
        $beforeEvent = new Event('View.beforeRenderFile', $this, array($viewFile));

        $eventManager->dispatch($beforeEvent);
        $content = $this->_evaluate($viewFile, $data);

        $afterEvent = new Event('View.afterRenderFile', $this, array($viewFile, $content));
        $eventManager->dispatch($afterEvent);
        if (isset($afterEvent->result)) {
            $content = $afterEvent->result;
        }

        return $content;
    }

    /**
     * Sandbox method to evaluate a template / view script in.
     *
     * @param string $viewFile    Filename of the view
     * @param array  $dataForView Data to include in rendered view.
     *                            If empty the current View::$viewVars will be used.
     *
     * @return string Rendered output
     */
    protected function _evaluate($viewFile, $dataForView)
    {
        $this->__viewFile = $viewFile;
        ob_start();

        $twig = $this->twig->loadTemplate($this->__viewFile);
        echo $twig->render($dataForView);

        unset($this->__viewFile);
        return ob_get_clean();
    }

    /**
     * Renders an element and fires the before and afterRender callbacks for it
     * and writes to the cache if a cache is used
     *
     * @param string $file    Element file path
     * @param array  $data    Data to render
     * @param array  $options Element options
     *
     * @return string
     */
    protected function _renderElement($file, $data, $options)
    {
        $current = $this->_current;
        $restore = $this->_currentType;
        $this->_currentType = static::TYPE_ELEMENT;

        if ($options['callbacks']) {
            $this->eventManager()->dispatch(new Event('View.beforeRender', $this, array($file)));
        }

        $element = $this->_render($this->getRelativePath($file), array_merge($this->viewVars, $data));

        if ($options['callbacks']) {
            $this->eventManager()->dispatch(new Event('View.afterRender', $this, array($file, $element)));
        }

        $this->_currentType = $restore;
        $this->_current = $current;

        return $element;
    }

    protected function getRelativePath($abs)
    {
        $output = $abs;
        foreach ($this->paths as $path) {
            if (strpos($abs, $path['path']) !== false) {
                if ($path['namespace'] == Twig_Loader_Filesystem::MAIN_NAMESPACE) {
                    $output = str_replace($path['path'], '', $abs);
                } else {
                    $output = '@' . $path['namespace'] . DS . str_replace($path['path'], '', $abs);
                }
            }
        }

        return $output;
    }

    /**
     * Returns filename of given action's template file (.ctp) as a string.
     * CamelCased action names will be under_scored! This means that you can have
     * LongActionNames that refer to long_action_names.ctp views.
     *
     * @param string $name Controller action to find template filename for
     *
     * @return string Template filename
     * @throws \Cake\View\Exception\MissingViewException when a view file could not be found.
     */
    protected function getViewFileName($name = null)
    {
        $subDir = null;

        if ($this->subDir !== null) {
            $subDir = $this->subDir . DS;
        }

        if ($name === null) {
            $name = $this->view;
        }
        $name = str_replace('/', DS, $name);
        list($plugin, $name) = $this->pluginSplit($name);

        if (strpos($name, DS) === false && $name[0] !== '.') {
            $name = $this->viewPath . DS . $subDir . Inflector::underscore($name);
        } elseif (strpos($name, DS) !== false) {
            if ($name[0] === DS || $name[1] === ':') {
                if (is_file($name)) {
                    return $name;
                }
                $name = trim($name, DS);
            } elseif (!$plugin || $this->viewPath !== $this->name) {
                $name = $this->viewPath . DS . $subDir . $name;
            } else {
                $name = DS . $subDir . $name;
            }
        }

        $paths = Hash::merge(
            $this->twig->getLoader()->getPaths($this->getPluginNamespace()),
            $this->twig->getLoader()->getPaths()
        );

        foreach ($paths as $path) {
            if (file_exists($path . DS . $name . $this->_ext)) {
                $filename = $name . $this->_ext;

                if ($this->plugin) {
                    $filename = $this->getPluginNamespace(true) . DS . $filename;
                }

                return $filename;
            }
        }

        throw new MissingViewException(array('file' => $name . $this->_ext));
    }

    /**
     * Get twig filesystem loader
     *
     * @return Twig_Loader_Filesystem
     * @throws \Twig_Error_Loader
     */
    protected function getFilesystemLoader()
    {
        $mainPath = CAKE . 'Template';
        $appPath = APP . 'Template';
        $this->paths[] = ['path' => $mainPath, 'namespace' => Twig_Loader_Filesystem::MAIN_NAMESPACE];
        $this->paths[] = ['path' => $appPath, 'namespace' => Twig_Loader_Filesystem::MAIN_NAMESPACE];
        $filesystemLoader = new Twig_Loader_Filesystem([$appPath, $mainPath]);

        if ($this->plugin) {
            $pluginTemplatePath = Plugin::classPath($this->plugin) . 'Template';
            $appPluginPath = $appPath . 'Plugin' . DS . $this->plugin;

            if (is_dir($pluginTemplatePath)) {
                $this->paths[] = ['path' => $pluginTemplatePath, 'namespace' => $this->getPluginNamespace()];

                if ($this->theme) {
                    $filesystemLoader->addPath(
                        Plugin::classPath($this->theme) . 'Template',
                        Inflector::underscore($this->plugin)
                    );
                }
                $filesystemLoader->addPath($pluginTemplatePath, $this->getPluginNamespace());
            }

            if (is_dir($appPluginPath)) {
                $this->paths[] = ['path' => $appPath, 'namespace' => $this->getPluginNamespace()];

                $filesystemLoader->addPath($appPluginPath, $this->getPluginNamespace());
            }
        }

        return $filesystemLoader;
    }

    /**
     * Add default extensions
     */
    protected function addExtensions()
    {
        $this->twig->addExtension(new Twig_Extension_Basic());
        $this->twig->addExtension(new Twig_Extension_I18n());
    }

    /**
     * Add default globals
     */
    protected function addGlobals()
    {
        $this->twig->addGlobal('View', $this);
        $this->twig->addGlobal('Configure', new StaticCaller('Cake\\Core\\Configure'));
        $this->twig->addGlobal('Router', new StaticCaller('Cake\\Routing\\Router'));
    }

    /**
     * Get plugin namespace
     *
     * @param bool $withSign At sign (@) symbol
     *
     * @return string
     */
    protected function getPluginNamespace($withSign = false)
    {
        if ($withSign) {
            return '@' . Inflector::underscore($this->plugin);
        } else {
            return Inflector::underscore($this->plugin);
        }
    }
}
