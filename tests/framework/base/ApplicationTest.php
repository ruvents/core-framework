<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\base;

use org\bovigo\vfs\vfsStream;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Module;
use yii\log\Dispatcher;
use yiiunit\TestCase;

/**
 * @group base
 */
class ApplicationTest extends TestCase
{
    private $originalErrorReporting;

    protected function setUp(): void
    {
        $this->originalErrorReporting = error_reporting();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        error_reporting($this->originalErrorReporting);
    }


    public function testContainerSettingsAffectBootstrap()
    {
        $this->mockApplication([
            'container' => [
                'definitions' => [
                    Dispatcher::className() => DispatcherMock::className(),
                ],
            ],
            'bootstrap' => ['log'],
        ]);

        $this->assertInstanceOf(DispatcherMock::className(), Yii::$app->log);
    }

    public function testBootstrap()
    {
        Yii::getLogger()->flush();


        $this->mockApplication([
            'components' => [
                'withoutBootstrapInterface' => [
                    'class' => Component::className(),
                ],
                'withBootstrapInterface' => [
                    'class' => BootstrapComponentMock::className(),
                ],
            ],
            'modules' => [
                'moduleX' => [
                    'class' => Module::className(),
                ],
            ],
            'bootstrap' => [
                'withoutBootstrapInterface',
                'withBootstrapInterface',
                'moduleX',
                function () {
                },
            ],
        ]);
        $this->assertSame('Bootstrap with yii\base\Component', Yii::getLogger()->messages[0][0]);
        $this->assertSame('Bootstrap with yiiunit\framework\base\BootstrapComponentMock::bootstrap()', Yii::getLogger()->messages[1][0]);
        $this->assertSame('Loading module: moduleX', Yii::getLogger()->messages[2][0]);
        $this->assertSame('Bootstrap with yii\base\Module', Yii::getLogger()->messages[3][0]);
        $this->assertSame('Bootstrap with Closure', Yii::getLogger()->messages[4][0]);
    }

    // public function testEnvironmentFile()
    // {
    //     $this->expectException(\yii\base\InvalidConfigException::class);
    //     $this->expectExceptionMessage('The environment file does not exist: test');
    //
    //     $this->mockApplication([
    //         'environmentFile' => 'test'
    //     ]);
    // }

    public function testEnvironmentFile()
    {
        $fs = vfsStream::setup();
        $fsEnvironmentFile = vfsStream::newFile('.env')
            ->setContent("TEST_PARAM_01=ONE\n#TEST_PARAM_02=TWO\n;TEST_PARAM_03=TRE")
            ->at($fs);

        $this->mockApplication([
            'environmentFile' => $fsEnvironmentFile->url(),
        ]);

        $this->assertEquals('ONE', getenv('TEST_PARAM_01'));
        $this->assertEquals('TWO', getenv('#TEST_PARAM_02'));
        $this->assertFalse(getenv(';TEST_PARAM_03'));
        $this->assertFalse(getenv('TEST_PARAM_03'));

        putenv('TEST_PARAM_01');
        putenv('#TEST_PARAM_02');

        $this->assertFalse(getenv('TEST_PARAM_01'));

        $this->mockApplication([
            'environmentFile' => '',
        ]);
    }

    public function testEnvironmentFile_absentWarning()
    {
        $fs = vfsStream::setup();
        $fsEnvironmentFile = "{$fs->url()}/.env";

        error_reporting(E_ALL & E_WARNING);

        $this->expectWarning();
        $this->expectWarningMessage('parse_ini_file(vfs://root/.env): failed to open stream: "org\bovigo\vfs\vfsStreamWrapper::stream_open" call failed');
        $this->assertFileNotExists($fsEnvironmentFile);

        $this->mockApplication([
            'environmentFile' => $fsEnvironmentFile,
        ]);
    }

    public function testEnvironmentFile_absentException()
    {
        $fs = vfsStream::setup();
        $fsEnvironmentFile = "{$fs->url()}/.env";

        error_reporting(E_ALL ^ E_WARNING);

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage("The environment file does not exist: {$fsEnvironmentFile}");

        $this->mockApplication([
            'environmentFile' => $fsEnvironmentFile,
        ]);
    }

    public function testEnvironmentFile_badContentWarning()
    {
        $fs = vfsStream::setup();
        $fsEnvironmentFile = vfsStream::newFile('.env')
            ->setContent('&TEST_PARAM_01=ONE')
            ->at($fs);

        error_reporting(E_ALL & E_WARNING);

        $this->expectWarning();
        $this->expectWarningMessage("syntax error, unexpected '&' in vfs://root/.env on line 1");

        $this->mockApplication([
            'environmentFile' => $fsEnvironmentFile->url(),
        ]);
    }

    public function testEnvironmentFile_badContentException()
    {
        $fs = vfsStream::setup();
        $fsEnvironmentFile = vfsStream::newFile('.env')
            ->setContent('&TEST_PARAM_01=ONE')
            ->at($fs);

        error_reporting(E_ALL ^ E_WARNING);

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('Error reading the environment file');

        $this->mockApplication([
            'environmentFile' => $fsEnvironmentFile->url(),
        ]);
    }

    public function testEnvironmentFile_emptyContent()
    {
        $fs = vfsStream::setup();
        $fsEnvironmentFile = vfsStream::newFile('.env')
            ->setContent('')
            ->at($fs);

        $this->mockApplication([
            'environmentFile' => $fsEnvironmentFile->url(),
        ]);

        $this->assertTrue(true);
    }

    public function testEnvironmentFile_isDirectoryError()
    {
        $fs = vfsStream::setup();
        $fsEnvironmentFile = vfsStream::newDirectory('.env')
            ->at($fs);

        error_reporting(E_ALL & E_WARNING);

        $this->expectWarning();
        $this->expectWarningMessage('parse_ini_file(vfs://root/.env): failed to open stream: "org\bovigo\vfs\vfsStreamWrapper::stream_open" call failed');

        $this->mockApplication([
            'environmentFile' => $fsEnvironmentFile->url(),
        ]);

        $this->assertTrue(true);
    }

    public function testEnvironmentFile_isDirectoryException()
    {
        $fs = vfsStream::setup();
        $fsEnvironmentFile = vfsStream::newDirectory('.env')
            ->at($fs);

        error_reporting(E_ALL ^ E_WARNING);

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('Error reading the environment file');

        $this->mockApplication([
            'environmentFile' => $fsEnvironmentFile->url(),
        ]);

        $this->assertTrue(true);
    }
}

class DispatcherMock extends Dispatcher
{
}

class BootstrapComponentMock extends Component implements BootstrapInterface
{
    public function bootstrap($app)
    {
    }
}
