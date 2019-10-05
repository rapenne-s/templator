<?php
// src/Controller/LuckyController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
//use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;



use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;


class IndexController extends AbstractController
{
    public function index()
    {
        $jinjas = $this->getParameter('templates.jinja');
        //$configs = $this->getParameter('templates.config');

        $files = glob($jinjas . '/*');
        $filenames = array_map('basename', $files);

        //var_dump($filenames, $files);
        return $this->render('index/list.html', array('filenames' => $filenames));
        //return new Response('', 200);
    }

    public function ff(Request $request)
    {
      $jinjas = $this->getParameter('templates.jinja');
      $requestedFilename = $request->request->get('file');
      if(!preg_match('/^[a-zA-Z0-9\.-]+$/', $requestedFilename))
      {
        return new Response('error, request filename "'.$requestedFilename . '" not found', 404);
      }
      return $this->redirectToRoute('gen', array('file' => $requestedFilename));
    }

    public function f(Request $request)
    {
      $jinjas = $this->getParameter('templates.jinja');
      $configs = $this->getParameter('templates.config');

      $formData = $request->request->get('form');
      $requestedFilename = $request->attributes->get('file');

      if(!preg_match('/^[a-zA-Z0-9\.-]+$/', $requestedFilename))
      {
        return new Response('error, request filename "'.$requestedFilename . '" not found', 404);
      }

      if(!file_exists($jinjas . '/' . $requestedFilename))
      {
        return new Response('Template file not found', 404);
      }

      $configFilename = str_replace('.sls', '.yaml', $requestedFilename);

      if(!file_exists($configs . '/' . $configFilename))
      {
        return new Response('Config file not found', 404);
      }

      $config = Yaml::parseFile($configs . '/' . $configFilename);
      //var_dump($config);
      $formBuilder = $this->createFormBuilder();

      if(!isset($config['vars']))
      {
        throw new \Exception('Config file '.$configFilename.' does not contains "vars:"');
      }

      $templateVars = array();
      foreach($config['vars'] as $key => $varData)
      {
        $templateVars[$key] = $varData['default'];
        switch($varData['type'])
        {
          case 'int':
            $type = IntegerType::class;
            $formBuilder->add($key, $type, array('data' => $varData['default']));
            break;
          case 'string':
            $type = TextType::class;
            $required = $varData['required'] ?? true;
            $options = array('data' => $varData['default'], 'required' => $required);
            if(isset($varData['help']))
            {
              $options['help'] = $varData['help'];
            }
            $formBuilder->add($key, $type, $options);
            break;
          case 'select':
            $type = ChoiceType::class;
            $choices = array_combine($varData['values'], $varData['values']);
            $formBuilder->add($key, $type, array('choices' => $choices));
            break;
          case 'password':
            $type = TextType::class;
            $password = '';
            $passwordHash = '';
            exec('pwgen 20 1', $password);
            exec('echo "'.$password[0].'" | openssl passwd -stdin -6', $passwordHash);

            $formBuilder->add($key,           $type, array('data' => $password[0]));
            $formBuilder->add($key . '_hash', HiddenType::class, array('data' => $passwordHash[0]));
            $templateVars[$key . '_hash'] = $passwordHash[0];
            break;
          default:
            throw new \Exception('Type '.$varData['type'].' not managed');
        }
      }
      $formBuilder->add('file', HiddenType::class, array('data' => $requestedFilename));
      $formBuilder->add('Submit', SubmitType::class);
      $form = $formBuilder->getForm();


      $form->handleRequest($request);

      if ($form->isSubmitted() && $form->isValid()) {
          // data is an array with "name", "email", and "message" keys
          $data = $form->getData();
          //var_dump('submitted');
          //var_dump($data);
          $templateVars = array_merge($templateVars, $data);
      }

      $render = $this->render(str_replace('../templates', '', $jinjas . '/' . $requestedFilename), $templateVars);

      return $this->render(
        'index/template.html',
        array(
          'config' => $config,
          'form' => $form->createView(),
          'render' => $render->getContent()
        )
      );
    }
}
