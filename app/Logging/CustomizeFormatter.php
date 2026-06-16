<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Auth;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Logger as MonoLogger;

/**
 * カスタムロギングフォーマッタークラス(コピペ)
 *
 * @see https://qiita.com/_hiro_dev/items/cea556897a36fcec31bf
 */
class CustomizeFormatter
{
    private string $dateFormat = 'Y-m-d H:i:s';

    public function __invoke(Logger $logger): void
    {
        $format = '[%datetime%] %channel%.%level_name% (%extra.class%:%extra.line%) %extra.function%: %message% %context% ' .
            json_encode([
                'ipAddress' => '%extra.ip%',
                'userId' => '%extra.userid%',
                'userName' => '%extra.username%',
                'memoryUsage' => '%extra.memory_usage%',
                'version' => config('yokakit.version'),
            ]) . PHP_EOL;

        // ログのフォーマットと日付のフォーマットを指定する
        $lineFormatter = new LineFormatter($format, $this->dateFormat, true, true);
        // IntrospectionProcessorを使うとextraフィールドが使えるようになる
        $ip = new IntrospectionProcessor(MonoLogger::DEBUG, ['Illuminate\\']);
        // WebProcessorを使うとextra.ipが使えるようになる
        $wp = new WebProcessor();
        // MemoryUsageProcessorを使うとextra.memory_usageが使えるようになる
        $mup = new MemoryUsageProcessor();

        /** @var array<int,StreamHandler> */
        $handlers = $logger->getHandlers();
        foreach ($handlers as $handler) {
            $handler->setFormatter($lineFormatter);
            // pushProcessorするとextra情報をログに埋め込んでくれる
            $handler->pushProcessor($ip);
            $handler->pushProcessor($wp);
            $handler->pushProcessor($mup);
            // addExtraFields()を呼び出す。extra.useridとextra.usernameをログに埋め込んでくれる
            $handler->pushProcessor([$this, 'addExtraFields']);
        }
    }

    /**
     * Undocumented function
     *
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    public function addExtraFields(array $record): array
    {
        $user = Auth::user();
        $record['extra']['userid'] = $user->id ?? null;
        $record['extra']['username'] = $user ? $user->name : null;
        return $record;
    }
}
