<?php

namespace Chubbyphp\Tests\Validation;

use Chubbyphp\Translation\TranslatorInterface;
use Chubbyphp\Validation\Rules\UniqueModelRule;
use Chubbyphp\Validation\ValidatableModelInterface;
use Chubbyphp\Validation\RequirementInterface;
use Chubbyphp\Validation\Validator;
use Psr\Log\LoggerInterface;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Rules\Email;
use Respect\Validation\Rules\NotEmpty;
use Respect\Validation\Validator as v;

/**
 * @covers Chubbyphp\Validation\Validator
 */
final class ValidatorTest extends \PHPUnit_Framework_TestCase
{
    public function testValidateModelWhichGotNoValidators()
    {
        $logger = $this->getLogger();
        $translator = $this->getTranslator();

        $validator = new Validator([], $translator, $logger);

        $user = $this->getUser(['id' => 'id1', 'email' => 'firstname.lastname@domain.tld']);

        $errors = $validator->validateModel($user);

        self::assertSame([], $errors);

        self::assertCount(0, $translator->__translates);

        self::assertCount(0, $logger->__logs);
    }

    public function testValidateModelWhichGotAModelValidator()
    {
        $logger = $this->getLogger();
        $translator = $this->getTranslator();

        $validator = new Validator([
            $this->getRequirements(true),
        ], $translator, $logger);

        $respectValidator = $this->getRespectValidator([
            ['return' => true],
        ]);

        $respectValidator->addRule($this->getUniqueModelRule());

        $user = $this->getUser(
            [
                'id' => 'id1',
                'email' => 'firstname.lastname@domain.tld',
            ],
            $respectValidator
        );

        $errors = $validator->validateModel($user);

        self::assertSame([], $errors);

        self::assertCount(0, $translator->__translates);

        self::assertCount(0, $logger->__logs);
    }

    public function testValidateModelWhichGotAModelValidatorWithException()
    {
        $logger = $this->getLogger();
        $translator = $this->getTranslator();

        $validator = new Validator([
            $this->getRequirements(true),
        ], $translator, $logger);

        $nestedException = $this->getNestedValidationException([
            $this->getValidationException(['properties' => ['email']], 'Unique Model'),
            $this->getValidationException([], 'Something else is weird'),
        ]);

        $respectValidator = $this->getRespectValidator([
            ['exception' => $nestedException],
        ]);

        $respectValidator->addRule($this->getUniqueModelRule());

        $user = $this->getUser(
            [
                'id' => 'id1',
                'email' => 'firstname.lastname@domain.tld',
            ],
            $respectValidator
        );

        $errors = $validator->validateModel($user);

        self::assertSame(
            [
                'email' => ['Unique Model'],
                '__model' => ['Something else is weird'],
            ],
            $errors
        );

        self::assertCount(2, $translator->__translates);

        self::assertSame('de', $translator->__translates[0]['locale']);
        self::assertSame('Unique Model', $translator->__translates[0]['key']);

        self::assertSame('de', $translator->__translates[1]['locale']);
        self::assertSame('Something else is weird', $translator->__translates[1]['key']);

        self::assertCount(2, $logger->__logs);
        self::assertSame('notice', $logger->__logs[0]['level']);
        self::assertSame('validation: field {field}, value {value}, message {message}', $logger->__logs[0]['message']);
        self::assertSame(
            ['field' => 'email', 'value' => '', 'message' => 'Unique Model'],
            $logger->__logs[0]['context']
        );
        self::assertSame('notice', $logger->__logs[1]['level']);
        self::assertSame('validation: field {field}, value {value}, message {message}', $logger->__logs[1]['message']);
        self::assertSame(
            ['field' => '__model', 'value' => '', 'message' => 'Something else is weird'],
            $logger->__logs[1]['context']
        );
    }

    public function testValidateModelWhichGotAPropertyValidators()
    {
        $logger = $this->getLogger();
        $translator = $this->getTranslator();

        $validator = new Validator([], $translator, $logger);

        $respectEmailValidator = $this->getRespectValidator([
            ['return' => true],
        ]);

        $respectEmailValidator->addRule(new NotEmpty())->addRule($this->getEmail());

        $respectPasswordValidator = $this->getRespectValidator([
            ['return' => true],
        ]);

        $respectPasswordValidator->addRule($this->getNotEmpty());

        $user = $this->getUser(
            [
                'id' => 'id1',
                'email' => 'firstname.lastname@domain.tld',
                'password' => 'password',
            ],
            null,
            ['email' => $respectEmailValidator, 'password' => $respectPasswordValidator]
        );

        $errors = $validator->validateModel($user);

        self::assertSame([], $errors);

        self::assertCount(0, $translator->__translates);

        self::assertCount(0, $logger->__logs);
    }

    public function testValidateModelWhichGotAPropertyValidatorsWithException()
    {
        $logger = $this->getLogger();
        $translator = $this->getTranslator();

        $validator = new Validator([], $translator, $logger);

        $nestedEmailException = $this->getNestedValidationException([
            $this->getValidationException([], 'Empty email'),
            $this->getValidationException([], 'Invalid E-Mail Address'),
        ]);

        $respectEmailValidator = $this->getRespectValidator([
            ['exception' => $nestedEmailException],
        ]);

        $respectEmailValidator->addRule(new NotEmpty())->addRule($this->getEmail());

        $nestedPasswordException = $this->getNestedValidationException([
            $this->getValidationException([], 'Empty password'),
        ]);

        $respectPasswordValidator = $this->getRespectValidator([
            ['exception' => $nestedPasswordException],
        ]);

        $respectPasswordValidator->addRule($this->getNotEmpty());

        $user = $this->getUser(
            [
                'id' => 'id1',
                'email' => '',
                'password' => '',
            ],
            null,
            ['email' => $respectEmailValidator, 'password' => $respectPasswordValidator]
        );

        $errors = $validator->validateModel($user);

        self::assertSame(
            [
                'email' => ['Empty email', 'Invalid E-Mail Address'],
                'password' => ['Empty password'],
            ],
            $errors
        );

        self::assertCount(3, $translator->__translates);

        self::assertSame('de', $translator->__translates[0]['locale']);
        self::assertSame('Empty email', $translator->__translates[0]['key']);

        self::assertSame('de', $translator->__translates[1]['locale']);
        self::assertSame('Invalid E-Mail Address', $translator->__translates[1]['key']);

        self::assertSame('de', $translator->__translates[2]['locale']);
        self::assertSame('Empty password', $translator->__translates[2]['key']);

        self::assertCount(3, $logger->__logs);
        self::assertSame('notice', $logger->__logs[0]['level']);
        self::assertSame(
            'validation: field {field}, value {value}, message {message}',
            $logger->__logs[0]['message']
        );
        self::assertSame(
            ['field' => 'email', 'value' => '', 'message' => 'Empty email'], $logger->__logs[0]['context']
        );
        self::assertSame('notice', $logger->__logs[1]['level']);
        self::assertSame(
            'validation: field {field}, value {value}, message {message}',
            $logger->__logs[1]['message']
        );
        self::assertSame(
            ['field' => 'email', 'value' => '', 'message' => 'Invalid E-Mail Address'], $logger->__logs[1]['context']
        );
        self::assertSame('notice', $logger->__logs[2]['level']);
        self::assertSame(
            'validation: field {field}, value {value}, message {message}',
            $logger->__logs[2]['message']
        );
        self::assertSame(
            ['field' => 'password', 'value' => '', 'message' => 'Empty password'], $logger->__logs[2]['context']
        );
    }

    public function testValidateArray()
    {
        $logger = $this->getLogger();
        $translator = $this->getTranslator();

        $validator = new Validator([], $translator, $logger);

        $respectEmailValidator = $this->getRespectValidator([
            ['return' => true],
        ]);

        $respectEmailValidator->addRule($this->getEmail());

        $respectPasswordValidator = $this->getRespectValidator([
            ['return' => true],
        ]);

        $respectPasswordValidator->addRule($this->getNotEmpty());

        $errors = $validator->validateArray(
            ['email' => 'firstname.lastname@domain.tld', 'password' => 'password'],
            ['email' => $respectEmailValidator, 'password' => $respectPasswordValidator]
        );

        self::assertSame([], $errors);

        self::assertCount(0, $translator->__translates);

        self::assertCount(0, $logger->__logs);
    }

    public function testValidateInvalidArray()
    {
        $logger = $this->getLogger();
        $translator = $this->getTranslator();

        $validator = new Validator([], $translator, $logger);

        $nestedEmailException = $this->getNestedValidationException([
            $this->getValidationException([], 'Empty email'),
            $this->getValidationException([], 'Invalid E-Mail Address'),
        ]);

        $respectEmailValidator = $this->getRespectValidator([
            ['exception' => $nestedEmailException],
        ]);

        $respectEmailValidator->addRule($this->getEmail());

        $nestedPasswordException = $this->getNestedValidationException([
            $this->getValidationException([], 'Empty password'),
        ]);

        $respectPasswordValidator = $this->getRespectValidator([
            ['exception' => $nestedPasswordException],
        ]);

        $respectPasswordValidator->addRule($this->getNotEmpty());

        $errors = $validator->validateArray(
            ['email' => '', 'password' => ''],
            ['email' => $respectEmailValidator, 'password' => $respectPasswordValidator]
        );

        self::assertSame(
            [
                'email' => ['Empty email', 'Invalid E-Mail Address'],
                'password' => ['Empty password'],
            ],
            $errors
        );

        self::assertCount(3, $translator->__translates);

        self::assertSame('de', $translator->__translates[0]['locale']);
        self::assertSame('Empty email', $translator->__translates[0]['key']);

        self::assertSame('de', $translator->__translates[1]['locale']);
        self::assertSame('Invalid E-Mail Address', $translator->__translates[1]['key']);

        self::assertSame('de', $translator->__translates[2]['locale']);
        self::assertSame('Empty password', $translator->__translates[2]['key']);

        self::assertCount(3, $logger->__logs);
        self::assertSame('notice', $logger->__logs[0]['level']);
        self::assertSame(
            'validation: field {field}, value {value}, message {message}',
            $logger->__logs[0]['message']
        );
        self::assertSame(
            ['field' => 'email', 'value' => '', 'message' => 'Empty email'], $logger->__logs[0]['context']
        );
        self::assertSame('notice', $logger->__logs[1]['level']);
        self::assertSame(
            'validation: field {field}, value {value}, message {message}',
            $logger->__logs[1]['message']
        );
        self::assertSame(
            ['field' => 'email', 'value' => '', 'message' => 'Invalid E-Mail Address'], $logger->__logs[1]['context']
        );
        self::assertSame('notice', $logger->__logs[2]['level']);
        self::assertSame(
            'validation: field {field}, value {value}, message {message}',
            $logger->__logs[2]['message']
        );
        self::assertSame(
            ['field' => 'password', 'value' => '', 'message' => 'Empty password'], $logger->__logs[2]['context']
        );
    }

    /**
     * @param array  $properties
     * @param v|null $modelValidator
     * @param array  $fieldValidators
     *
     * @return ValidatableModelInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getUser(
        array $properties,
        v $modelValidator = null,
        array $fieldValidators = []
    ): ValidatableModelInterface {
        $user = $this
            ->getMockBuilder(ValidatableModelInterface::class)
            ->setMethods(['getId', 'getModelValidator', 'getPropertyValidators'])
            ->getMockForAbstractClass()
        ;

        foreach ($properties as $field => $value) {
            $user->$field = $value;
        }

        $user->expects(self::any())->method('getId')->willReturn($user->id);
        $user->expects(self::any())->method('getModelValidator')->willReturn($modelValidator);
        $user->expects(self::any())->method('getPropertyValidators')->willReturn($fieldValidators);

        return $user;
    }

    /**
     * @param bool $isResponsible
     *
     * @return RequirementInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getRequirements(bool $isResponsible): RequirementInterface
    {
        /** @var RequirementInterface|\PHPUnit_Framework_MockObject_MockObject $helper */
        $helper = $this
            ->getMockBuilder(RequirementInterface::class)
            ->setMethods(['isResponsible', 'help'])
            ->getMockForAbstractClass();

        $helper
            ->expects(self::any())
            ->method('isResponsible')
            ->willReturnCallback(function (AbstractRule $rule, $value) use ($isResponsible) {
                return $isResponsible;
            });

        $helper
            ->expects(self::any())
            ->method('help')
            ->willReturnCallback(function (AbstractRule $rule, $value) {
            });

        return $helper;
    }
    /**
     * @param array $assertStack
     *
     * @return v|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getRespectValidator(array $assertStack = []): v
    {
        $respectValidator = $this
            ->getMockBuilder(v::class)
            ->disableOriginalConstructor()
            ->setMethods(['addRule', 'getRules', 'assert'])
            ->getMockForAbstractClass();

        $respectValidator->__rules = [];
        $respectValidator
            ->expects(self::any())
            ->method('addRule')
            ->willReturnCallback(function (AbstractRule $rule) use ($respectValidator) {
                $respectValidator->__rules[] = $rule;

                return $respectValidator;
            })
        ;

        $respectValidator
            ->expects(self::any())
            ->method('getRules')
            ->willReturnCallback(function () use ($respectValidator) {
                return $respectValidator->__rules;
            })
        ;

        $assertCount = 0;
        $respectValidator
            ->expects(self::any())
            ->method('assert')
            ->willReturnCallback(function ($value) use (&$assertStack, &$assertCount) {
                $assert = array_shift($assertStack);

                self::assertNotNull(
                    $assert,
                    sprintf('There is no assert info within $assertStack at %d call.', $assertCount)
                );

                if (isset($assert['exception'])) {
                    throw $assert['exception'];
                }

                return $assert['return'];
            })
        ;

        return $respectValidator;
    }

    /**
     * @return UniqueModelRule|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getUniqueModelRule(): UniqueModelRule
    {
        $uniqueModelRule = $this
            ->getMockBuilder(UniqueModelRule::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $uniqueModelRule;
    }

    /**
     * @return Email|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getEmail(): Email
    {
        $emailRule = $this
            ->getMockBuilder(Email::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $emailRule;
    }

    /**
     * @return NotEmpty|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getNotEmpty(): NotEmpty
    {
        $notEmptyRule = $this
            ->getMockBuilder(NotEmpty::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $notEmptyRule;
    }

    /**
     * @param ValidationException[]|array $childrenExceptions
     *
     * @return NestedValidationException|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getNestedValidationException(array $childrenExceptions): NestedValidationException
    {
        $nestedException = $this
            ->getMockBuilder(NestedValidationException::class)
            ->disableOriginalConstructor()
            ->setMethods(['getIterator'])
            ->getMock();

        $nestedException
            ->expects(self::any())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator($childrenExceptions))
        ;

        return $nestedException;
    }

    /**
     * @param array $params
     *
     * @return ValidationException|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getValidationException(array $params, string $mainMessage)
    {
        $exception = $this
            ->getMockBuilder(ValidationException::class)
            ->disableOriginalConstructor()
            ->setMethods(['hasParam', 'getParam', 'setParam', 'getMainMessage'])
            ->getMock();

        $exception->__params = $params;

        $exception
            ->expects(self::any())
            ->method('hasParam')
            ->willReturnCallback(function (string $param) use ($exception) {
                return isset($exception->__params[$param]);
            })
        ;

        $exception
            ->expects(self::any())
            ->method('getParam')
            ->willReturnCallback(function (string $param) use ($exception) {
                return $exception->__params[$param];
            })
        ;

        $exception
            ->expects(self::any())
            ->method('setParam')
            ->willReturnCallback(function (string $param, $value) use ($exception) {
                $exception->__params[$param] = $value;
            })
        ;

        $exception
            ->expects(self::any())
            ->method('getMainMessage')
            ->willReturnCallback(function () use ($exception, $mainMessage) {
                if (isset($exception->__params['translator'])) {
                    $translator = $exception->__params['translator'];
                    $mainMessage = $translator($mainMessage);
                }

                return $mainMessage;
            })
        ;

        return $exception;
    }

    private function getTranslator(): TranslatorInterface
    {
        /** @var TranslatorInterface|\PHPUnit_Framework_MockObject_MockObject $translator */
        $translator = $this
            ->getMockBuilder(TranslatorInterface::class)
            ->setMethods(['translate'])
            ->getMockForAbstractClass();

        $translator->__translates = [];

        $translator
            ->expects(self::any())
            ->method('translate')
            ->willReturnCallback(function (string $locale, string $key) use ($translator) {
                $translator->__translates[] = [
                    'locale' => $locale,
                    'key' => $key,
                ];

                return $key;
            })
        ;

        return $translator;
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        $methods = [
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug',
        ];

        /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->setMethods(array_merge($methods, ['log']))
            ->getMockForAbstractClass()
        ;

        $logger->__logs = [];

        foreach ($methods as $method) {
            $logger
                ->expects(self::any())
                ->method($method)
                ->willReturnCallback(
                    function (string $message, array $context = []) use ($logger, $method) {
                        $logger->log($method, $message, $context);
                    }
                )
            ;
        }

        $logger
            ->expects(self::any())
            ->method('log')
            ->willReturnCallback(
                function (string $level, string $message, array $context = []) use ($logger) {
                    $logger->__logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
                }
            )
        ;

        return $logger;
    }
}
