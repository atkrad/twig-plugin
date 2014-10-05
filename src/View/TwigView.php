<?php

namespace Twig\View;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Utility\Inflector;
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
     * @param string $view Name of view file to use
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
        if ($view !== false && $viewFileName = $this->getViewFilename()) {
            $this->_currentType = static::TYPE_VIEW;
            $this->eventManager()->dispatch(new Event('View.beforeRender', $this, array($viewFileName)));
            $renderedTemplate = $this->_render($this->getViewFilename());
            $this->eventManager()->dispatch(new Event('View.afterRender', $this, array($viewFileName)));
        }

        $this->hasRendered = true;
        return $renderedTemplate;
    }

    /**
     * Renders and returns output for given view filename with its
     * array of data. Handles parent/extended views.
     *
     * @param string $viewFile Filename of the view
     * @param array $data Data to include in rendered view. If empty the current View::$viewVars will be used.
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
     * @param string $viewFile Filename of the view
     * @param array $dataForView Data to include in rendered view.
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
     * @param string $file Element file path
     * @param array $data Data to render
     * @param array $options Element options
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

    protected function getViewFilename()
    {
        if ($this->plugin) {
            $filename = '@' . Inflector::underscore($this->plugin)
                . DS . $this->viewPath
                . DS . $this->view . $this->_ext;
        } else {
            $filename = $this->viewPath . DS . $this->view . $this->_ext;
        }

        return $filename;
    }

    protected function getFilesystemLoader()
    {
        $mainPath = APP . 'Template' . DS;
        $this->paths[] = ['path' => $mainPath, 'namespace' => Twig_Loader_Filesystem::MAIN_NAMESPACE];
        $filesystemLoader = new Twig_Loader_Filesystem($mainPath);

        foreach (Plugin::loaded() as $plugin) {
            $templatePath = Plugin::classPath($plugin) . 'Template' . DS;

            if (is_dir($templatePath)) {
                $this->paths[] = ['path' => $templatePath, 'namespace' => Inflector::underscore($plugin)];

                if ($this->theme) {
                    $filesystemLoader->addPath(
                        Plugin::classPath($this->theme) . 'Template' . DS,
                        Inflector::underscore($plugin)
                    );
                }
                $filesystemLoader->addPath($templatePath, Inflector::underscore($plugin));
            }
        }

        return $filesystemLoader;
    }

    protected function addExtensions()
    {
        $this->twig->addExtension(new Twig_Extension_Basic());
        $this->twig->addExtension(new Twig_Extension_I18n());
    }

    protected function addGlobals()
    {
        $this->twig->addGlobal('View', $this);
        $this->twig->addGlobal('Configure', new StaticCaller('Cake\\Core\\Configure'));
        $this->twig->addGlobal('Router', new StaticCaller('Cake\\Routing\\Router'));
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
}
