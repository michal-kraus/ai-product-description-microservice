<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GenerateProductDescriptionRequest
{
    public const MAX_NAME_LENGTH = 200;
    public const MAX_FEATURES_LENGTH = 2000;

    public function __construct(
        #[Assert\NotBlank(message: 'The "name" field is required and cannot be empty.', normalizer: 'trim')]
        #[Assert\Length(
            max: self::MAX_NAME_LENGTH,
            maxMessage: 'Input too long. Maximum length: name={{ limit }} characters.',
        )]
        public string $name = '',
        #[Assert\NotBlank(message: 'The "features" field is required and cannot be empty.', normalizer: 'trim')]
        #[Assert\Length(
            max: self::MAX_FEATURES_LENGTH,
            maxMessage: 'Input too long. Maximum length: features={{ limit }} characters.',
        )]
        public string $features = '',
    ) {}
}
