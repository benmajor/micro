# Slim 3 Controller

Slim 3 ControllerThis package adds controller support to the Slim 3 framework. This can be particularly useful when building MVC applications using Slim and the native Slim Twig View extension.

### Installation:

Installation can be achieved via Composer as follows:

```bash
composer require benmajor/slim3-controller
```

### Usage:

**For the purposes of this documentation, all file references are defined relative to the root in which Slim3 is installed.**

You should start by extending the `Controller` class within your own project to add custom functionality for the project. Below is a simple example of an custom Controller (notice the use of namespacing):

/controllers/Contact.php:

```php
<?php

namespace MyApp\App\Controller;

use BenMajor\Slim3Controller\Controller;

class ContactController extends Controller
{
  public function index()
  {
    return $this->render('contact-main.twig', [ /* view data here */ ]);
  }
  
  public function send()
  {
    // Handle the sending of the contact form.
  }
}
```

In order to make use of the `ContactController`, you must define it for use along with your other application routes. The example below shows how to leverage Controller support:

/routes.php

```php
<?php

use MyApp\App\Controller;

// Define the controllers:
$controllers = [
  'contact' => new ContactController($app)
];

$app->group('/contact', function() use ($app, $controllers) {
  $app->get('/', $controllers['contact']('index'));
  $app->post('/', $controllers['contact']('send'));
});
```

### Acknowledgements

Thanks to [@martynbiz](https://github.com/martynbiz/) for his awesome work on the original [Slim 3 Controller](https://github.com/martynbiz/slim3-controller).
