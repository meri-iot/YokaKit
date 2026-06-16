<?php

namespace Tests\Browser;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ExampleTest extends DuskTestCase
{
    protected function driver()
    {
        // $options = (new ChromeOptions)->addArguments([
        //     '--disable-gpu',
        //     '--headless',
        //     // '--no-sandbox',
        //     // '--proxy-server=http://10.0.0.2:8080'
        // ]);

        // return RemoteWebDriver::create(
        //     'http://10.4.5.209:4444/wd/hub', DesiredCapabilities::chrome()->setCapability(
        //         ChromeOptions::CAPABILITY, $options
        //     )
        // );

        return RemoteWebDriver::create('http://10.4.5.209:4444/wd/hub', DesiredCapabilities::chrome());
    }

    /**
     * A basic browser test example.
     *
     * @return void
     */
    public function testBasicExample()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/home')
                ->assertSee('YokaKit');
        });
    }
}
