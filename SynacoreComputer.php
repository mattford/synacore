<?php
require_once('SynacoreException.php');
class SynacoreComputer
{
    public array $registers = [];
    public array $stack = [];
    public bool $halted = false;

    private int $ip = 0;

    private array $input = [];

    public function __construct(private array $program)
    {}

    public function run()
    {
        $methods = [
            0 => [$this, 'halt'],
            1 => [$this, 'set'],
            2 => [$this, 'push'],
            3 => [$this, 'pop'],
            4 => [$this, 'eq'],
            5 => [$this, 'gt'],
            6 => [$this, 'jmp'],
            7 => [$this, 'jt'],
            8 => [$this, 'jf'],
            9 => [$this, 'add'],
            10 => [$this, 'mult'],
            11 => [$this, 'mod'],
            12 => [$this, 'and'],
            13 => [$this, 'or'],
            14 => [$this, 'not'],
            15 => [$this, 'rmem'],
            16 => [$this, 'wmem'],
            17 => [$this, 'call'],
            18 => [$this, 'ret'],
            19 => [$this, 'out'],
            20 => [$this, 'in'],
        ];
        while (!$this->halted && !empty($this->program[$this->ip])) {
            $instruction = $this->program[$this->ip];
            $method = $methods[$instruction] ?? null;
            if ($method) {
                $method();
            } else {
                $this->ip++;
            }
        }
    }

    /**
     * halt: 0
     * stop execution and terminate the program
     * @return void
     */
    private function halt()
    {
        $this->halted = true;
    }

    /**
     * set: 1 a b
     * set register <a> to the value of <b>
     * @return void
     */
    private function set()
    {
        [$a, $b] = array_slice($this->program, $this->ip + 1, 2);
        $this->registers[$this->getRegisterId($a)] = $this->getValue($b);
        $this->ip += 3;
    }

    /**
     * push: 2 a
     * push <a> onto the stack
     * @return void
     */
    private function push()
    {
        [$a] = array_slice($this->program, $this->ip + 1, 1);
        $this->stack[] = $this->getValue($a);
        $this->ip += 2;
    }

    /**
     * pop: 3 a
     * remove the top element from the stack and write it into <a>; empty stack = error
     * @return void
     * @throws SynacoreException
     */
    private function pop()
    {
        if (empty($this->stack)) {
            throw new SynacoreException("Empty stack encountered, cannot pop");
        }
        [$a] = array_slice($this->program, $this->ip + 1, 1);
        $this->registers[$this->getRegisterId($a)] = array_pop($this->stack);
        $this->ip += 2;
    }

    /**
     * eq: 4 a b c
     * set <a> to 1 if <b> is equal to <c>; set it to 0 otherwise
     * @return void
     */
    private function eq()
    {
        [$a, $b, $c] = array_slice($this->program, $this->ip + 1, 3);;
        $result = $this->getValue($b) === $this->getValue($c);
        $this->registers[$this->getRegisterId($a)] = (int)$result;
        $this->ip += 4;
    }

    /**
     * gt: 5 a b c
     * set <a> to 1 if <b> is greater than <c>; set it to 0 otherwise
     * @return void
     */
    private function gt()
    {
        [$a, $b, $c] = array_slice($this->program, $this->ip + 1, 3);
        $result = $this->getValue($b) > $this->getValue($c);
        $this->registers[$this->getRegisterId($a)] = (int)$result;
        $this->ip += 4;
    }

    /**
     * jmp: 6 a
     * jump to <a>
     * @return void
     */
    private function jmp()
    {
        [$a] = array_slice($this->program, $this->ip + 1, 1);
        $this->ip = $this->getValue($a);
    }

    /**
     * jt: 7 a b
     * if <a> is nonzero, jump to <b>
     */
    private function jt()
    {
        [$a, $b] = array_slice($this->program, $this->ip + 1, 2);
        if ($this->getValue($a) != 0) {
            $this->ip = $this->getValue($b);
        } else {
            $this->ip += 3;
        }
    }

    /**
     * jf: 8 a b
     * if <a> is zero, jump to <b>
     */
    private function jf()
    {
        [$a, $b] = array_slice($this->program, $this->ip + 1, 2);
        if ($this->getValue($a) == 0) {
            $this->ip = $this->getValue($b);
        } else {
            $this->ip += 3;
        }
    }

    /**
     * add: 9 a b c
     * assign into <a> the sum of <b> and <c> (modulo 32768)
     * @return void
     */
    private function add()
    {
        [$a, $b, $c] = array_slice($this->program, $this->ip + 1, 3);
        $this->registers[$this->getRegisterId($a)] = ($this->getValue($b) + $this->getValue($c)) % 32768;
        $this->ip += 4;
    }

    /**
     * mult: 10 a b c
     * store into <a> the product of <b> and <c> (modulo 32768)
     * @return void
     */
    private function mult()
    {
        [$a, $b, $c] = array_slice($this->program, $this->ip + 1, 3);
        $this->registers[$this->getRegisterId($a)] = ($this->getValue($b) * $this->getValue($c)) % 32768;
        $this->ip += 4;
    }

    /**
     * mod: 11 a b c
     * store into <a> the remainder of <b> divided by <c>
     */
    private function mod()
    {
        [$a, $b, $c] = array_slice($this->program, $this->ip + 1, 3);
        $this->registers[$this->getRegisterId($a)] = $this->getValue($b) % $this->getValue($c);
        $this->ip += 4;
    }

    /**
     * and: 12 a b c
     * stores into <a> the bitwise and of <b> and <c>
     * @return void
     */
    private function and()
    {
        [$a, $b, $c] = array_slice($this->program, $this->ip + 1, 3);
        $this->registers[$this->getRegisterId($a)] = $this->getValue($b) & $this->getValue($c);
        $this->ip += 4;
    }

    /**
     * or: 13 a b c
     * stores into <a> the bitwise or of <b> and <c>
     */
    private function or()
    {
        [$a, $b, $c] = array_slice($this->program, $this->ip + 1, 3);
        $this->registers[$this->getRegisterId($a)] = $this->getValue($b) | $this->getValue($c);
        $this->ip += 4;
    }

    /**
     * not: 14 a b
     * stores 15-bit bitwise inverse of <b> in <a>
     */
    private function not()
    {
        [$a, $b] = array_slice($this->program, $this->ip + 1, 2);
        $this->registers[$this->getRegisterId($a)] = $this->getValue($b) ^ (32768-1);
        $this->ip += 3;
    }

    /**
     * rmem: 15 a b
     * read memory at address <b> and write it to <a>
     */
    private function rmem()
    {
        [$a, $b] = array_slice($this->program, $this->ip + 1, 2);
        $this->registers[$this->getRegisterId($a)] = $this->program[$this->getValue($b)];
        $this->ip += 3;
    }

    /**
     * wmem: 16 a b
     * write the value from <b> into memory at address <a>
     */
    private function wmem()
    {
        [$a, $b] = array_slice($this->program, $this->ip + 1, 2);
        $this->program[$this->getValue($a)] = $this->getValue($b);
        $this->ip += 3;
    }

    /**
     * call: 17 a
     * write the address of the next instruction to the stack and jump to <a>
     */
    private function call()
    {
        [$a] = array_slice($this->program, $this->ip + 1, 1);
        $this->stack[] = $this->ip + 2;
        $this->ip = $this->getValue($a);
    }

    /**
     * ret: 18
     * remove the top element from the stack and jump to it; empty stack = halt
     */
    private function ret()
    {
        if (empty($this->stack)) {
            $this->halt();
            return;
        }
        $this->ip = array_pop($this->stack);
    }

    /**
     * out: 19 a
     * write the character represented by ascii code <a> to the terminal
     */
    private function out()
    {
        [$a] = array_slice($this->program, $this->ip + 1, 1);
        echo chr($this->getValue($a));
        $this->ip += 2;
    }

    /**
     * in: 20 a
     * read a character from the terminal and write its ascii code to <a>; it can be assumed that once input starts, it will continue until a newline is encountered; this means that you can safely read whole lines from the keyboard and trust that they will be fully read
     */
    private function in()
    {
        [$a] = array_slice($this->program, $this->ip + 1, 1);
        if (empty($this->input)) {
            $inLine = readline();
            if ($this->handleInput($inLine)) {
                return;
            }
            $this->input = str_split($inLine);
            $this->input[] = "\n";
        }
        $char = array_shift($this->input);
        $this->registers[$this->getRegisterId($a)] = ord($char);
        $this->ip += 2;
    }

    private function isRegister(int $value): bool
    {
        return $value >= 32768 && $value <= 32775;
    }

    private function getRegisterId(int $value): int
    {
        return $value - 32768;
    }

    private function getValue(int $value): int
    {
        if ($this->isRegister($value)) {
            return $this->registers[$this->getRegisterId($value)] ?? 0;
        }
        return $value;
    }

    private function handleInput(string $in): bool
    {
        if (stripos($in, "save") === 0) {
            $this->saved = serialize([$this->program, $this->ip, $this->registers]);
            return true;
        }
        if (stripos($in, "load") === 0) {
            [$this->program, $this->ip, $this->registers] = unserialize($this->saved);
            return true;
        }
        return false;
    }
}
