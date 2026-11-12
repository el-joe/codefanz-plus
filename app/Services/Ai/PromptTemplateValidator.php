<?php

namespace App\Services\Ai;

use App\Models\PromptTemplate;

/**
 * Template variable rules: names are snake_case and unique, the system template may only use trusted built-in
 * variables (so staff input can never reach the system prompt), the user template may only use declared variables
 * (plus the language helpers), and every declared variable must be used.
 */
class PromptTemplateValidator
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    private const USER_HELPERS = ['language', 'languages'];

    /**
     * @param  list<array{name?: mixed}>  $variables
     * @return array<string, string> field => message
     */
    public function errors(string $systemTemplate, string $userTemplate, array $variables): array
    {
        $errors = [];
        $names = [];

        foreach ($variables as $index => $variable) {
            $name = (string) ($variable['name'] ?? '');

            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                $errors["variables.{$index}.name"] = __('Use lowercase letters, digits and underscores, starting with a letter.');
            } elseif (in_array($name, [...PromptTemplate::SYSTEM_VARIABLES, ...self::USER_HELPERS], true)) {
                $errors["variables.{$index}.name"] = __('“:name” is reserved for built-in values.', ['name' => $name]);
            } elseif (in_array($name, $names, true)) {
                $errors["variables.{$index}.name"] = __('“:name” is declared twice.', ['name' => $name]);
            }

            $names[] = $name;
        }

        $unknownInSystem = array_diff(PromptTemplate::placeholders($systemTemplate), PromptTemplate::SYSTEM_VARIABLES);

        if ($unknownInSystem !== []) {
            $errors['system_template'] = __('The system prompt may only use built-in variables. Not allowed: :names', ['names' => implode(', ', $unknownInSystem)]);
        }

        $used = PromptTemplate::placeholders($userTemplate);
        $undeclared = array_diff($used, $names, self::USER_HELPERS);

        if ($undeclared !== []) {
            $errors['user_template'] = __('Declare these variables or remove them: :names', ['names' => implode(', ', $undeclared)]);
        }

        $unused = array_diff(array_filter($names), $used);

        if ($unused !== [] && ! isset($errors['user_template'])) {
            $errors['user_template'] = __('These declared variables are not used in the user prompt: :names', ['names' => implode(', ', $unused)]);
        }

        return $errors;
    }
}
