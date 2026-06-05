<?php

namespace Tests\Feature;

use Tests\TestCase;

class AboutPageTest extends TestCase
{
  /**
   * /about ページが未ログイン状態でも正常に表示されることをテストします。
   */
  public function test_about_page_renders_successfully(): void
  {
    $response = $this->get(route('about'));

    $response->assertStatus(200);
    $response->assertSee('SkLoG について');
    $response->assertSee('アプリパスワードの取り扱いについて');
  }
}
