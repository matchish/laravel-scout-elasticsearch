<?php


namespace Matchish\ScoutElasticSearch\Console\Commands;


use Symfony\Component\Console\Helper\ProgressBar;

class DefaultProgressBar
{
    /**
     * @var ProgressBar
     */
    private $origin;

    /**
     * @param ProgressBar $origin
     */
    public function __construct(ProgressBar $origin)
    {
        $this->origin = $origin;
    }

    public function start(int $max = null)
    {
        $this->origin->setBarCharacter('<fg=green>⚬</>');
        $this->origin->setEmptyBarCharacter("<fg=red>⚬</>");
        $this->origin->setProgressCharacter("<fg=green>➤</>");
        $this->origin->setFormat(
            "%message%\n%current%/%max% [%bar%] %percent:3s%%\n"
        );
        $this->origin->start($max);
    }

    public function finish(): void
    {
        $this->origin->finish();
    }

    public function setMessage(string $message, string $name = 'message')
    {
        $this->origin->setMessage($message, $name);
    }

    public function advance(int $step = 1)
    {
        $this->origin->advance($step);
    }
}
