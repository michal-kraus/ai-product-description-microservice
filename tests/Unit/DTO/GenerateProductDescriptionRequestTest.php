<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\GenerateProductDescriptionRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GenerateProductDescriptionRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testItValidatesSuccessfullyWithValidData(): void
    {
        $dto = new GenerateProductDescriptionRequest(
            name: 'Mechanical Keyboard',
            features: 'Cherry MX Red, RGB Backlight',
        );

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations);
        $this->assertSame('Mechanical Keyboard', $dto->name);
        $this->assertSame('Cherry MX Red, RGB Backlight', $dto->features);
    }

    public function testItViolatesNotBlankWhenFieldsAreEmpty(): void
    {
        $dto = new GenerateProductDescriptionRequest(
            name: '',
            features: '',
        );

        $violations = $this->validator->validate($dto);
        $this->assertCount(2, $violations);
    }

    public function testItViolatesNotBlankWhenFieldsAreWhitespaceOnly(): void
    {
        $dto = new GenerateProductDescriptionRequest(
            name: '   ',
            features: '   ',
        );

        $violations = $this->validator->validate($dto);
        $this->assertCount(2, $violations);
    }

    public function testItViolatesLengthWhenNameExceedsLimit(): void
    {
        $dto = new GenerateProductDescriptionRequest(
            name: str_repeat('a', GenerateProductDescriptionRequest::MAX_NAME_LENGTH + 1),
            features: 'Valid features',
        );

        $violations = $this->validator->validate($dto);
        $this->assertCount(1, $violations);
        $this->assertSame('name', $violations->get(0)->getPropertyPath());
    }

    public function testItViolatesLengthWhenFeaturesExceedLimit(): void
    {
        $dto = new GenerateProductDescriptionRequest(
            name: 'Valid Name',
            features: str_repeat('f', GenerateProductDescriptionRequest::MAX_FEATURES_LENGTH + 1),
        );

        $violations = $this->validator->validate($dto);
        $this->assertCount(1, $violations);
        $this->assertSame('features', $violations->get(0)->getPropertyPath());
    }
}
