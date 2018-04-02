<?php

declare(strict_types=1);

$uses = [
    'use '.$userClass.';',
    'use '.$formNs.'\\ResetPasswordType;',
    'use MsgPhp\\User\\Command\\ChangeUserCredentialCommand;',
    'use Doctrine\\ORM\\EntityManagerInterface;',
    'use SimpleBus\\SymfonyBridge\\Bus\\CommandBus;',
    'use Symfony\\Component\\Form\\FormFactoryInterface;',
    'use Symfony\\Component\\HttpFoundation\\Request;',
    'use Symfony\\Component\\HttpFoundation\\RedirectResponse;',
    'use Symfony\\Component\\HttpFoundation\\Response;',
    'use Symfony\\Component\\HttpFoundation\\Session\\Flash\\FlashBagInterface;',
    'use Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException;',
    'use Symfony\\Component\\Routing\\Annotation\\Route;',
    'use Twig\\Environment;',
];

$userShortName = false === ($i = strrpos($userClass, '\\')) ? $userClass : substr($userClass, $i + 1);

sort($uses);
$uses = implode("\n", $uses);

return <<<PHP
<?php

declare(strict_types=1);

namespace ${ns};

${uses}

/**
 * @Route("/reset-password/{token}", name="reset_password")
 */
final class ResetPasswordController
{
    public function __invoke(
        string \$token,
        Request \$request,
        FormFactoryInterface \$formFactory,
        FlashBagInterface \$flashBag,
        Environment \$twig,
        CommandBus \$bus,
        EntityManagerInterface \$em
    ): Response {
        \$user = \$em->getRepository(${userShortName}::class)->findOneBy(['passwordResetToken' => \$token]);

        if (!\$user instanceof ${userShortName}) {
            throw new NotFoundHttpException();
        }

        \$form = \$formFactory->createNamed('', ResetPasswordType::class);
        \$form->handleRequest(\$request);

        if (\$form->isSubmitted() && \$form->isValid()) {
            \$bus->handle(new ChangeUserCredentialCommand(\$user->getId(), ['password' => \$form->get('password')->getData()]));
            \$flashBag->add('success', 'You\'re password is changed.');

            return new RedirectResponse('/');
        }

        return new Response(\$twig->render('${template}', [
            'form' => \$form->createView(),
        ]));
    }
}
PHP;
