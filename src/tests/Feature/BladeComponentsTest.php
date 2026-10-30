<?php

namespace Tests\Feature;

use Tests\TestCase;

class BladeComponentsTest extends TestCase
{
    public function test_delete_form_emits_a_js_safe_inline_confirm_for_awkward_messages(): void
    {
        $view = $this->blade('<x-delete-form action="/things/1" :confirm="$message" />', [
            'message' => 'Delete O\'Brien "the great"?',
        ]);

        // form-guards.js runs this attribute as code, so quotes must survive as JS escapes
        // rather than terminating the string or the attribute.
        $view->assertSee("onsubmit=\"return confirm('Delete O".'\\'.'u0027Brien '.'\\'.'u0022the great'.'\\'."u0022?')\"", false);
        $view->assertSee('action="/things/1"', false);
        $view->assertSee('name="_method" value="DELETE"', false);
    }

    public function test_delete_form_button_variant_renders_the_danger_button(): void
    {
        $view = $this->blade('<x-delete-form action="/x" confirm="Sure?" variant="button" label="Delete Asset" />');

        $view->assertSee('bg-red-600', false);
        $view->assertSee('Delete Asset');
    }

    public function test_btn_renders_an_anchor_with_href_and_a_button_without(): void
    {
        $anchor = $this->blade('<x-btn href="/go">Go</x-btn>');
        $anchor->assertSee('<a ', false);
        $anchor->assertSee('href="/go"', false);
        $anchor->assertDontSee('<button', false);

        $button = $this->blade('<x-btn type="submit">Save</x-btn>');
        $button->assertSee('<button ', false);
        $button->assertSee('type="submit"', false);
        $button->assertDontSee('<a ', false);
    }

    public function test_btn_falls_back_to_secondary_for_an_unknown_variant(): void
    {
        $this->blade('<x-btn variant="nope">X</x-btn>')->assertSee('bg-white dark:bg-gray-700', false);
    }

    public function test_form_card_spoofs_the_method_only_when_not_post(): void
    {
        $put = $this->blade('<x-form-card action="/a" method="PUT" submit="Save" cancel="/back">fields</x-form-card>');
        $put->assertSee('name="_method" value="PUT"', false);
        $put->assertSee('href="/back"', false);

        $post = $this->blade('<x-form-card action="/a" submit="Add" cancel="/back">fields</x-form-card>');
        $post->assertDontSee('name="_method"', false);
        $post->assertSee('Add');
    }
}
