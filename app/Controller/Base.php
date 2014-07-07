<?php

namespace Controller;

use Core\Registry;
use Core\Security;
use Core\Translator;
use Model\LastLogin;

/**
 * Base controller
 *
 * @package  controller
 * @author   Frederic Guillot
 * @property \Model\Acl         $acl
 * @property \Model\Action      $action
 * @property \Model\Board       $board
 * @property \Model\Category    $category
 * @property \Model\Comment     $comment
 * @property \Model\Config      $config
 * @property \Model\File        $file
 * @property \Model\Google      $google
 * @property \Model\GitHub      $gitHub
 * @property \Model\LastLogin   $lastLogin
 * @property \Model\Ldap        $ldap
 * @property \Model\Project     $project
 * @property \Model\RememberMe  $rememberMe
 * @property \Model\SubTask     $subTask
 * @property \Model\Task        $task
 * @property \Model\User        $user
 */
abstract class Base
{
    /**
     * Request instance
     *
     * @accesss public
     * @var \Core\Request
     */
    public $request;

    /**
     * Response instance
     *
     * @accesss public
     * @var \Core\Response
     */
    public $response;

    /**
     * Template instance
     *
     * @accesss public
     * @var \Core\Template
     */
    public $template;

    /**
     * Session instance
     *
     * @accesss public
     * @var \Core\Session
     */
    public $session;

    /**
     * Registry instance
     *
     * @access private
     * @var \Core\Registry
     */
    private $registry;

    /**
     * Constructor
     *
     * @access public
     * @param  \Core\Registry  $registry   Registry instance
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Load automatically models
     *
     * @access public
     * @param  string $name Model name
     * @return mixed
     */
    public function __get($name)
    {
        $class = '\Model\\'.ucfirst($name);
        $this->registry->$name = new $class($this->registry->shared('db'), $this->registry->shared('event'));
        return $this->registry->shared($name);
    }

    /**
     * Method executed before each action
     *
     * @access public
     */
    public function beforeAction($controller, $action)
    {
        // Start the session
        $this->session->open(BASE_URL_DIRECTORY, SESSION_SAVE_PATH);

        // HTTP secure headers
        $this->response->csp(array('style-src' => "'self' 'unsafe-inline'"));
        $this->response->nosniff();
        $this->response->xss();
        $this->response->hsts();
        $this->response->xframe();

        // Load translations
        $language = $this->config->get('language', 'en_US');
        if ($language !== 'en_US') Translator::load($language);

        // Set timezone
        date_default_timezone_set($this->config->get('timezone', 'UTC'));

        // Authentication
        if (! $this->acl->isLogged() && ! $this->acl->isPublicAction($controller, $action)) {

            // Try the remember me authentication first
            if (! $this->rememberMe->authenticate()) {

                // Redirect to the login form if not authenticated
                $this->response->redirect('?controller=user&action=login');
            }
            else {

                $this->lastLogin->create(
                    LastLogin::AUTH_REMEMBER_ME,
                    $this->acl->getUserId(),
                    $this->user->getIpAddress(),
                    $this->user->getUserAgent()
                );
            }
        }
        else if ($this->rememberMe->hasCookie()) {
            $this->rememberMe->refresh();
        }

        // Check if the user is allowed to see this page
        if (! $this->acl->isPageAccessAllowed($controller, $action)) {
            $this->response->redirect('?controller=user&action=forbidden');
        }

        // Attach events
        $this->action->attachEvents();
        $this->project->attachEvents();
    }

    /**
     * Application not found page (404 error)
     *
     * @access public
     */
    public function notfound()
    {
        $this->response->html($this->template->layout('app_notfound', array('title' => t('Page not found'))));
    }

    /**
     * Application forbidden page
     *
     * @access public
     */
    public function forbidden()
    {
        $this->response->html($this->template->layout('app_forbidden', array('title' => t('Access Forbidden'))));
    }

    /**
     * Check if the CSRF token from the URL is correct
     *
     * @access protected
     */
    protected function checkCSRFParam()
    {
        if (! Security::validateCSRFToken($this->request->getStringParam('csrf_token'))) {
            $this->forbidden();
        }
    }

    /**
     * Check if the current user have access to the given project
     *
     * @access protected
     * @param  integer   $project_id  Project id
     */
    protected function checkProjectPermissions($project_id)
    {
        if ($this->acl->isRegularUser()) {

            if ($project_id > 0 && ! $this->project->isUserAllowed($project_id, $this->acl->getUserId())) {
                $this->forbidden();
            }
        }
    }

    /**
     * Redirection when there is no project in the database
     *
     * @access protected
     */
    protected function redirectNoProject()
    {
        $this->session->flash(t('There is no active project, the first step is to create a new project.'));
        $this->response->redirect('?controller=project&action=create');
    }

    /**
     * Common layout for task views
     *
     * @access protected
     * @param  string $template Template name
     * @param  array $params Template parameters
     * @return string
     */
    protected function taskLayout($template, array $params)
    {
        $content = $this->template->load($template, $params);
        $params['task_content_for_layout'] = $content;

        return $this->template->layout('task_layout', $params);
    }

    /**
     * Common method to get a task for task views
     *
     * @access protected
     * @return array
     */
    protected function getTask()
    {
        $task = $this->task->getById($this->request->getIntegerParam('task_id'), true);

        if (! $task) {
            $this->notfound();
        }

        $this->checkProjectPermissions($task['project_id']);

        return $task;
    }
}
