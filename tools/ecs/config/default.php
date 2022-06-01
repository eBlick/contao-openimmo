<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestCaseStaticMethodCallsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->sets([__DIR__.'/../vendor/contao/easy-coding-standard/config/contao.php']);
    $ecsConfig->parallel();

    $ecsConfig->ruleWithConfiguration(HeaderCommentFixer::class, [
        'header' => "@copyright eBlick Medienberatung\n@license proprietary",
    ]);

    $ecsConfig->ruleWithConfiguration(
        PhpUnitTestCaseStaticMethodCallsFixer::class,
        ['call_type' => 'self']
    );

    $parameters = $ecsConfig->parameters();
    $parameters->set(Option::CACHE_DIRECTORY, sys_get_temp_dir().'/ecs_cache');
};
