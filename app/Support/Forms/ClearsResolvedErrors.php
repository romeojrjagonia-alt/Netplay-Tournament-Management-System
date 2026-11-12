<?php

namespace App\Support\Forms;

/**
 * Lets a Livewire form drop a field's validation message as soon as the field
 * is fixed, instead of waiting for the next submit.
 *
 * When a field that is showing an error changes, it is checked again against
 * the form's rules: the message goes away if the value is now acceptable, or
 * changes to whatever is still wrong. Fields that depend on it (a start and an
 * end date, a password and its confirmation) are rechecked too. Fields with no
 * error are left alone, so nobody is told off for something they haven't
 * finished filling in.
 *
 * The component supplies the rules with liveRules() and, optionally, which
 * fields depend on each other with pairedFields().
 */
trait ClearsResolvedErrors
{
    /**
     * Every rule the form can apply, keyed by property name.
     *
     * @return array<string, array<int, mixed>>
     */
    abstract protected function liveRules(): array;

    /**
     * Fields to recheck when a given field changes.
     *
     * @return array<string, list<string>>
     */
    protected function pairedFields(): array
    {
        return [];
    }

    public function updated(string $property): void
    {
        $rules = $this->liveRules();
        $pairs = $this->pairedFields();

        foreach ([$property, ...($pairs[$property] ?? [])] as $field) {
            if (isset($rules[$field]) && $this->getErrorBag()->has($field)) {
                $this->resetValidation($field);
                $this->validateOnly($field, [$field => $rules[$field]]);
            }
        }
    }
}
