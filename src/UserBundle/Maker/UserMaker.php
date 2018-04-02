<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\Maker;

use Doctrine\ORM\EntityManagerInterface;
use MsgPhp\Domain\Entity\Features;
use MsgPhp\Domain\Event\{DomainEventHandlerInterface, DomainEventHandlerTrait};
use MsgPhp\User\{CredentialInterface, Entity, UserIdInterface};
use Sensio\Bundle\FrameworkExtraBundle\Routing\AnnotatedRouteControllerLoader;
use SimpleBus\SymfonyBridge\Bus\CommandBus;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class UserMaker implements MakerInterface
{
    private $classMapping;
    private $projectDir;
    private $credential;
    private $configs = [];
    private $writes = [];

    public function __construct(array $classMapping, string $projectDir)
    {
        $this->classMapping = $classMapping;
        $this->projectDir = $projectDir;
    }

    public static function getCommandName(): string
    {
        return 'make:user';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $this->credential = null;
        $this->configs = $this->writes = [];

        if (!isset($this->classMapping[Entity\User::class])) {
            throw new \LogicException('User class not configured. Did you install the bundle using Symfony Recipes?');
        }

        $userClass = new \ReflectionClass($this->classMapping[Entity\User::class]);

        $this->generateUser($userClass, $io);
        $this->generateRole($userClass, $io);
        $this->generateControllers($io);

        if ($this->configs) {
            $this->writes[] = [$this->projectDir.'/config/packages/msgphp_user.make.php', self::getSkeleton('config.php', ['config' => var_export(array_merge_recursive(...$this->configs), true)])];
            $this->configs = [];
        }

        $writeAll = count($this->writes) > 1 && $io->confirm('Write all changes at once?');

        while ($write = array_shift($this->writes)) {
            [$fileName, $contents] = $write;

            switch ($writeAll ? 'y' : $io->choice(sprintf('Write changes to %s?', preg_replace('~^'.preg_quote($this->projectDir.'/', '~').'~', './', $fileName)), ['n' => 'No', 's' => 'No, show new code', 'y' => 'Yes'], 'Yes')) {
                case 'n':
                    continue 2;
                case 's':
                    $io->writeln($contents);
                    break;
                case 'y':
                default:
                    if (!is_dir($parent = dirname($fileName))) {
                        mkdir($parent, 0777, true);
                    }
                    file_put_contents($fileName, $contents);
                    break;
            }
        }

        $io->success('Done!');
    }

    private function generateUser(\ReflectionClass $class, ConsoleStyle $io): void
    {
        $lines = file($fileName = $class->getFileName());
        $traits = array_flip($class->getTraitNames());
        $implementors = array_flip($class->getInterfaceNames());
        $inClass = $inClassBody = $hasUses = $hasTraitUses = $hasImplements = false;
        $useLine = $traitUseLine = $implementsLine = $constructorLine = 0;
        $nl = null;
        $indent = '';

        foreach ($tokens = token_get_all(implode('', $lines)) as $i => $token) {
            if (!is_array($token)) {
                if ('{' === $token && $inClass && !$inClassBody) {
                    $inClassBody = true;
                }
                continue;
            }

            if ($inClassBody) {
                if (!$traitUseLine) {
                    $traitUseLine = $token[2];
                }
            } else {
                if (!$useLine) {
                    $useLine = $token[2];
                }
            }

            if (in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT, \T_WHITESPACE], true)) {
                if (\T_WHITESPACE === $token[0]) {
                    if (!$nl) {
                        $nl = in_array($nl = trim($token[1], ' '), ["\n", "\r", "\r\n"], true) ? $nl : null;
                    }
                    if (!$indent && $inClassBody && $nl) {
                        $spaces = explode($nl, $token[1]);
                        $indent = end($spaces);
                    }
                }
                continue;
            }

            if (\T_NAMESPACE === $token[0] && !$useLine) {
                $useLine = $token[2];
            } elseif (\T_CLASS === $token[0] && !$inClass) {
                $inClass = true;
            } elseif (\T_USE === $token[0]) {
                if (!$inClass) {
                    $useLine = $token[2];
                    $hasUses = true;
                } else {
                    $traitUseLine = $token[2];
                    $hasTraitUses = true;
                }
            } elseif (\T_EXTENDS === $token[0] && $inClass) {
                $implementsLine = $tokens[2];
                $j = $i + 1;
                while (isset($tokens[$j])) {
                    if (isset($tokens[$j][0]) && \T_STRING === $tokens[$j][0]) {
                        $implementsLine = $tokens[$j][2];
                    } elseif ('{' === $tokens[$j] || (isset($tokens[$j][0]) && \T_IMPLEMENTS === $tokens[$j][0])) {
                        break;
                    }
                    ++$j;
                }
            } elseif (\T_IMPLEMENTS === $token[0] && $inClass) {
                $hasImplements = true;
                $implementsLine = $token[2];
                $j = $i + 1;
                while (isset($tokens[$j])) {
                    if (is_array($tokens[$j]) && \T_STRING === $tokens[$j][0]) {
                        $implementsLine = $tokens[$j][2];
                    } elseif ('{' === $tokens[$j]) {
                        break;
                    }
                    ++$j;
                }
            } elseif (\T_FUNCTION === $token[0]) {
                $constructorLine = $token[2];
                $j = $i - 1;
                while (isset($tokens[$j])) {
                    if (is_array($tokens[$j])) {
                        $constructorLine = $tokens[$j][2];
                    } elseif (';' === $tokens[$j] || '}' === $tokens[$j]) {
                        break;
                    }
                    --$j;
                }
            }
        }
        if (!$constructorLine) {
            $constructorLine = $traitUseLine;
        }

        $nl = $nl ?? \PHP_EOL;
        $addUses = $addTraitUses = $addImplementors = [];
        $write = false;

        if (isset($this->classMapping[CredentialInterface::class])) {
            $this->credential = $this->classMapping[CredentialInterface::class];
        } elseif ($io->confirm('Generate a user credential?')) {
            $credentials = [];
            foreach (glob(dirname((new \ReflectionClass(UserIdInterface::class))->getFileName()).'/Entity/Credential/*.php') as $file) {
                if ('Anonymous' === $credential = basename($file, '.php')) {
                    continue;
                }
                $credentials[] = $credential;
            }

            $credential = $io->choice('Select credential type:', $credentials, 'EmailPassword');
            $credentialClass = $this->credential = 'MsgPhp\\User\\Entity\\Credential\\'.$credential;
            $credentialTrait = 'MsgPhp\\User\\Entity\\Features\\'.($credentialName = $credential.'Credential');
            $credentialSignature = self::getConstructorSignature(new \ReflectionClass($credentialClass));
            $credentialInit = '$this->credential = new '.$credential.'('.self::getSignatureVariables($credentialSignature).');';

            $addUses[$credentialClass] = true;
            if (!isset($traits[$credentialTrait])) {
                $addUses[$credentialTrait] = true;
                $addUses[DomainEventHandlerInterface::class] = true;
                $addUses[DomainEventHandlerTrait::class] = true;
                $addImplementors['DomainEventHandlerInterface'] = true;
                $addTraitUses[$credentialName] = true;
                $addTraitUses['DomainEventHandlerTrait'] = true;
            }

            if (null !== $constructor = $class->getConstructor()) {
                $offset = $constructor->getStartLine() - 1;
                $length = $constructor->getEndLine() - $offset;
                $contents = preg_replace_callback_array([
                    '~^[^_]*+__construct\([^\)]*+\)~i' => function (array $match) use ($credentialSignature): string {
                        $signature = substr($match[0], 0, -1);
                        if ('' !== $credentialSignature) {
                            $signature .= ('(' !== substr(rtrim($signature), -1) ? ', ' : '').$credentialSignature;
                        }

                        return $signature.')';
                    },
                    '~\s*+}\s*+$~s' => function ($match) use ($nl, $indent, $credential, $credentialInit): string {
                        $indent = ltrim(substr($match[0], 0, strpos($match[0], '}')), "\r\n").'    ';

                        return $nl.$indent.$credentialInit.$match[0];
                    },
                ], $oldContents = implode('', array_slice($lines, $offset, $length)));

                if ($contents !== $oldContents) {
                    array_splice($lines, $offset, $length, $contents);
                    $write = true;
                    if ($traitUseLine > $offset + 1) {
                        ++$traitUseLine;
                    }
                }
            } else {
                $constructor = array_map(function (string $line) use ($nl, $indent): string {
                    return $indent.$line.$nl;
                }, explode("\n", <<<PHP
public function __construct(${credentialSignature})
{
    ${credentialInit}
}
PHP
                ));
                array_unshift($constructor, $nl);
                array_splice($lines, $constructorLine, 0, $constructor);
                $write = true;
                if ($traitUseLine > $constructorLine) {
                    $traitUseLine += 4;
                }
            }

            if (!isset($traits[Entity\Features\ResettablePassword::class]) && $this->hasPassword() && $io->confirm('Can users reset their password?')) {
                $addUses[Entity\Features\ResettablePassword::class] = true;
                $addTraitUses['ResettablePassword'] = true;
                if (!isset($implementors[DomainEventHandlerInterface::class])) {
                    $addUses[DomainEventHandlerInterface::class] = true;
                    $addUses[DomainEventHandlerTrait::class] = true;
                    $addImplementors['DomainEventHandlerInterface'] = true;
                    $addTraitUses['DomainEventHandlerTrait'] = true;
                }
            }
        }

        if (!isset($traits[Features\CanBeEnabled::class]) && $io->confirm('Can users be enabled / disabled?')) {
            $implementors[] = DomainEventHandlerInterface::class;
            $addUses[Features\CanBeEnabled::class] = true;
            $addTraitUses['CanBeEnabled'] = true;
            if (!isset($implementors[DomainEventHandlerInterface::class])) {
                $addUses[DomainEventHandlerInterface::class] = true;
                $addUses[DomainEventHandlerTrait::class] = true;
                $addImplementors['DomainEventHandlerInterface'] = true;
                $addTraitUses['DomainEventHandlerTrait'] = true;
            }
        }

        if (!isset($traits[Features\CanBeConfirmed::class]) && $io->confirm('Can users be confirmed?')) {
            $implementors[] = DomainEventHandlerInterface::class;
            $addUses[Features\CanBeConfirmed::class] = true;
            $addTraitUses['CanBeConfirmed'] = true;
            if (!isset($implementors[DomainEventHandlerInterface::class])) {
                $addUses[DomainEventHandlerInterface::class] = true;
                $addUses[DomainEventHandlerTrait::class] = true;
                $addImplementors['DomainEventHandlerInterface'] = true;
                $addTraitUses['DomainEventHandlerTrait'] = true;
            }
        }

        // Can users have roles?

        if ($numUses = count($addUses)) {
            ksort($addUses);
            $uses = array_map(function (string $use) use ($nl): string {
                return 'use '.$use.';'.$nl;
            }, array_keys($addUses));
            if (!$hasUses) {
                $uses[] = $nl;
                ++$implementsLine;
                ++$traitUseLine;
            }
            array_splice($lines, $useLine, 0, $uses);
            $write = true;
            $implementsLine += $numUses;
            $traitUseLine += $numUses;
        }

        if ($numTraitUses = count($addTraitUses)) {
            ksort($addTraitUses);
            $traitUses = array_map(function (string $use) use ($nl, $indent): string {
                return $indent.'use '.$use.';'.$nl;
            }, array_keys($addTraitUses));
            if (!$hasTraitUses) {
                $traitUses[] = $nl;
            }
            array_splice($lines, $traitUseLine, 0, $traitUses);
            $write = true;
        }

        if ($numImplementors = count($addImplementors)) {
            ksort($addImplementors);
            $implements = ($hasImplements ? ', ' : ' implements ').implode(', ', array_keys($addImplementors));
            $lines[$implementsLine - 1] = preg_replace('~(\s*+{?\s*+)$~', $implements.'\\1', $lines[$implementsLine - 1], 1);
            $write = true;
        }

        if ($write) {
            $this->writes[] = [$fileName, implode('', $lines)];
        }
    }

    private function generateRole(\ReflectionClass $userClass, ConsoleStyle $io): void
    {
        if (isset($this->classMapping[Entity\Role::class]) || !$io->confirm('Enable user roles?')) {
            return;
        }

        $baseDir = dirname($userClass->getFileName());
        $vars = ['ns' => $ns = $userClass->getNamespaceName()];

        $this->writes[] = [$baseDir.'/Role.php', self::getSkeleton('entity/Role.php', $vars)];
        $this->writes[] = [$baseDir.'/UserRole.php', self::getSkeleton('entity/UserRole.php', $vars)];
        $this->configs[] = ['class_mapping' => [
            Entity\Role::class => $ns.'\\Role',
            Entity\UserRole::class => $ns.'\\UserRole',
        ]];
    }

    private function generateControllers(ConsoleStyle $io): void
    {
        if (!$this->credential || !$io->confirm('Generate controllers?')) {
            return;
        }

        if (
            !class_exists(AnnotatedRouteControllerLoader::class) ||
            !interface_exists(FormInterface::class) ||
            !interface_exists(ValidatorInterface::class) ||
            !class_exists(Environment::class) ||
            !class_exists(CommandBus::class) ||
            !interface_exists(EntityManagerInterface::class)
        ) {
            $io->note('Not all controller dependencies are met. Run `composer require annotations form validator twig simple-bus/symfony-bridge orm`');

            if (!$io->confirm('Continue anyway?')) {
                return;
            }
        }

        $nsForm = trim($io->ask('Provide the form namespace', 'App\\Form\\User\\'), '\\');
        $nsController = trim($io->ask('Provide the controller namespace', 'App\\Controller\\User\\'), '\\');
        $templateDir = trim($io->ask('Provide the base template directory', 'user/'), '/');
        $baseTemplate = ltrim($io->ask('Provide the base template file', 'base.html.twig'), '/');
        $baseTemplateBlock = $io->ask('Provide the base template block name', 'body');
        $usernameField = $this->credential::getUsernameField();
        $hasPassword = $this->hasPassword();

        if ($io->confirm('Add a login controller?')) {
            if (!class_exists(Security::class) && !$io->confirm('Symfony Security is not available. Continue anyway?')) {
                return;
            }

            $this->writes[] = [$this->getClassFileName($nsForm.'\\LoginType'), self::getSkeleton('form/LoginType.php', [
                'ns' => $nsForm,
                'hasPassword' => $hasPassword,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\LoginController'), self::getSkeleton('controller/LoginController.php', [
                'ns' => $nsController,
                'formNs' => $nsForm,
                'fieldName' => $usernameField,
                'template' => $template = $templateDir.'/login.html.twig',
            ])];
            $this->writes[] = [$this->getTemplateFileName($template), self::getSkeleton('template/login.html.php', [
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
                'hasPassword' => $hasPassword,
            ])];

            if ($io->confirm('Add config/packages/security.yaml?')) {
                $this->writes[] = [$this->projectDir.'/config/packages/security.yaml', self::getSkeleton('security.php', [
                    'fieldName' => $usernameField,
                ])];
            }
        }

        if ($io->confirm('Add a registration controller?')) {
            $this->writes[] = [$this->getClassFileName($nsForm.'\\RegisterType'), self::getSkeleton('form/RegisterType.php', [
                'ns' => $nsForm,
                'hasPassword' => $hasPassword,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\RegisterController'), self::getSkeleton('controller/RegisterController.php', [
                'ns' => $nsController,
                'formNs' => $nsForm,
                'fieldName' => $usernameField,
                'template' => $template = $templateDir.'/register.html.twig',
            ])];
            $this->writes[] = [$this->getTemplateFileName($template), self::getSkeleton('template/register.html.php', [
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
                'hasPassword' => $hasPassword,
            ])];
        }

        if ($hasPassword && $io->confirm('Add a forgot / reset password controller?')) {
            $this->writes[] = [$this->getClassFileName($nsForm.'\\ForgotPasswordType'), self::getSkeleton('form/ForgotPasswordType.php', [
                'ns' => $nsForm,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getClassFileName($nsForm.'\\ResetPasswordType'), self::getSkeleton('form/ResetPasswordType.php', [
                'ns' => $nsForm,
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\ForgotPasswordController'), self::getSkeleton('controller/ForgotPasswordController.php', [
                'ns' => $nsController,
                'formNs' => $nsForm,
                'fieldName' => $usernameField,
                'userClass' => $this->classMapping[Entity\User::class],
                'template' => $templateForgot = $templateDir.'/forgot_password.html.twig',
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\ResetPasswordController'), self::getSkeleton('controller/ResetPasswordController.php', [
                'ns' => $nsController,
                'formNs' => $nsForm,
                'userClass' => $this->classMapping[Entity\User::class],
                'template' => $templateReset = $templateDir.'/reset_password.html.twig',
            ])];
            $this->writes[] = [$this->getTemplateFileName($templateForgot), self::getSkeleton('template/forgot_password.html.php', [
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getTemplateFileName($templateReset), self::getSkeleton('template/reset_password.html.php', [
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
            ])];
        }
    }

    private static function getConstructorSignature(\ReflectionClass $class): string
    {
        if (null === $constructor = $class->getConstructor()) {
            return '';
        }

        $lines = file($class->getFileName());
        $offset = $constructor->getStartLine() - 1;
        $body = implode('', array_slice($lines, $offset, $constructor->getEndLine() - $offset));

        if (preg_match('~^[^_]*+__construct\(([^\)]++)\)~i', $body, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private static function getSignatureVariables(string $signature): string
    {
        preg_match_all('~(?:\.{3})?\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*~', $signature, $matches);

        return isset($matches[0][0]) ? implode(', ', $matches[0]) : '';
    }

    private static function getSkeleton(string $path, array $vars = [])
    {
        return (function () use ($path, $vars) {
            extract($vars);

            return require dirname(__DIR__).'/Resources/skeleton/'.$path;
        })();
    }

    private function getTemplateFileName(string $path): string
    {
        return $this->projectDir.'/templates/'.$path;
    }

    private function getClassFileName(string $class): string
    {
        if ('App\\' === substr($class, 0, 4)) {
            $class = substr($class, 4);
        }

        return $this->projectDir.'/src/'.str_replace('\\', '/', $class).'.php';
    }

    private function hasPassword(): bool
    {
        return $this->credential && false !== strpos($this->credential, 'Password');
    }
}
