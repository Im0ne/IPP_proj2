<?php

/**
 * IPP - PHP IPP24code interpreter
 * @author Ivan Onufriienko
 */

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\InternalErrorException;
use IPP\Core\Exception\IPPException;

use IPP\Core\ReturnCode;
use Throwable;

class Interpreter extends AbstractInterpreter
{
    private Executor $executor;

    public function execute(): int
    {
        $this->executor = new Executor($this);
        $this->executor->loadInstructions($this->source->getDomDocument());
        $this->executor->execute();
        return ReturnCode::OK; 
    }
    

    public function readInt(): ?int
    {
        return $this->input->readInt();
    }

    public function readString(): ?string
    {
        return $this->input->readString();
    }

    public function readBool(): ?bool
    {
        return $this->input->readBool();
    }

    public function writeInt(int $value): void
    {
        $this->stdout->writeInt($value);
    }

    public function writeString(string $value): void
    {
        $this->stdout->writeString($value);
    }

    public function writeBool(bool $value): void
    {
        $this->stdout->writeBool($value);
    }

    public function writeFloat(float $value): void
    {
        $this->stdout->writeFloat($value);
    }

}

/**
 * Exception class for unexpected XML structure errors.
 * This can occur for example when an argument element is outside an instruction element,
 * or when an instruction has a duplicate or negative order.
 * Corresponds to error code 32.
 */
class SourceStructureError extends IPPException
{
    public function __construct(string $message = "Invalid source structure", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::INVALID_SOURCE_STRUCTURE, $previous);
    }
}

/**
 * Exception class for semantic errors in the input IPPcode24.
 * This can occur for example when an undefined label is used, or a variable is redefined.
 * Corresponds to error code 52.
 */
class SemanticError extends IPPException
{
    public function __construct(string $message = "Semantic error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::SEMANTIC_ERROR, $previous);
    }
}

/**
 * Exception class for runtime interpretation errors due to incorrect operand types.
 * Corresponds to error code 53.
 */
class OperandTypeError extends IPPException
{
    public function __construct(string $message = "Operand type error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::OPERAND_TYPE_ERROR, $previous);
    }
}

/**
 * Exception class for runtime interpretation errors due to access to a non-existent variable
 * (the memory frame does exist).
 * Corresponds to error code 54.
 */
class VariableAccessError extends IPPException
{
    public function __construct(string $message = "Variable access error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::VARIABLE_ACCESS_ERROR, $previous);
    }
}

/**
 * Exception class for runtime interpretation errors due to a non-existent memory frame
 * (for example, reading from an empty stack of frames).
 * Corresponds to error code 55.
 */
class FrameAccessError extends IPPException
{
    public function __construct(string $message = "Frame access error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::FRAME_ACCESS_ERROR, $previous);
    }
}

/**
 * Exception class for runtime interpretation errors due to a missing value
 * (in a variable, on the data stack, or on the call stack).
 * Corresponds to error code 56.
 */
class ValueError extends IPPException
{
    public function __construct(string $message = "Value error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::VALUE_ERROR, $previous);
    }
}

/**
 * Exception class for runtime interpretation errors due to incorrect operand value
 * (for example, division by zero, incorrect return value of the EXIT instruction).
 * Corresponds to error code 57.
 */
class OperandValueError extends IPPException
{
    public function __construct(string $message = "Operand value error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::OPERAND_VALUE_ERROR, $previous);
    }
}

/**
 * Exception class for runtime interpretation errors due to incorrect string operations.
 * Corresponds to error code 58.
 */
class StringOperationError extends IPPException
{
    public function __construct(string $message = "String operation error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::STRING_OPERATION_ERROR, $previous);
    }
}

/**
 * The MemoryValue class represents a value in memory.
 * It contains the type and value of the memory value, and provides methods to get and set these properties.
 */
class MemoryValue
{
    // The type of the memory value
    public ?string $type = null;
    // The value of the memory value
    public mixed $value = null;

    /**
     * Gets the value of the memory value.
     * Throws a ValueError if the value is null.
     *
     * @return mixed The value of the memory value.
     */
    public function getVal(): mixed
    {
        if ($this->value === null) {throw new ValueError;}
        return $this->value;
    }

    /**
     * Gets the type of the memory value.
     * Throws a ValueError if the type is null.
     *
     * @return string|null The type of the memory value.
     */
    public function getType(): ?string
    {
        if ($this->type === null) {throw new ValueError;}
        return $this->type;
    }

    /**
     * Sets the value of the memory value.
     *
     * @param mixed $val The new value.
     */
    public function setVal(mixed $val): void
    {
        $this->value = $val;
    }

    /**
     * Sets the type of the memory value.
     *
     * @param string|null $type The new type.
     */
    public function setType(?string $type): void
    {
        $this->type = $type;
    }

}

/**
 * The Variable class represents a variable in the IPP24code language.
 * It extends the MemoryValue class and provides methods to get the value and type of the variable.
 */
class Variable extends MemoryValue
{
    /**
     * Gets the value of the variable.
     *
     * @return mixed The value of the variable.
     */
    public function getVal(): mixed
    {
        return $this->value;
    }

    /**
     * Gets the type of the variable.
     * Throws a ValueError if the type is null.
     *
     * @return string|null The type of the variable.
     */
    public function getType(): ?string
    {
        if ($this->type == null) {throw new ValueError;}
        return $this->type;
    }
}

/**
 * The Frame class represents a frame in the IPP24code language.
 * It maintains a map of variables and provides methods to get and define variables.
 */
class Frame
{
    // The map of variables in the frame
    /** @var Variable[] */
    private array $varmap;
    
    /**
     * Constructs a new Frame instance.
     */
    public function __construct() {
        $this->varmap = array();
    }

    /**
     * Gets a variable from the frame by its name.
     *
     * @param int|string $name The name of the variable.
     * @return Variable|null The variable, or null if the variable does not exist.
     */
    public function getVar(int|string $name): ?Variable
    {
        if (array_key_exists($name, $this->varmap)) {
            return $this->varmap[$name];
        } else {
            return null;
        }
    }

    /**
     * Defines a new variable in the frame with the given name.
     *
     * @param int|string $name The name of the variable.
     * @return Variable The new variable.
     */
    public function defVar(int|string $name): Variable
    {
        $this->varmap[$name] = new Variable(); 
        return $this->varmap[$name];      
    }
}

/**
 * The Executor class is responsible for executing the instructions.
 * It maintains the state of the execution, including the instruction pointer, frame stack, data stack, call stack, and label map.
 * It also provides methods to manipulate these states.
 */
class Executor
{
    private int|null $instructionPtr = 1;
    /** @var Frame[] */
    private array $frameStack = [];
    /** @var mixed[] */
    private array $dataStack = [];
    /** @var int[] */
    private array $callStack = [];
    private Frame $GF;
    /** @var int[] */
    private array $labelMap = [];
    /** @var Instruction[] */
    private array $instructionMap = [];
    private ?Frame $TF = null;
    public Interpreter $interpret;
    private int $lastInstructionOrder = 0;

    /**
     * Constructs a new Executor instance.
     * Initializes the global frame and the interpreter instance.
     *
     * @param Interpreter $interpret The interpreter instance.
     */
    public function __construct(Interpreter $interpret) 
    {
        $this->GF = new Frame();
        $this->interpret = $interpret;
    }

    /**
     * Pushes data onto the data stack.
     *
     * @param mixed $data The data to push.
     */
    public function pushData(mixed $data): void 
    {
        array_push($this->dataStack, $data);
    }

    /**
     * Pops data from the data stack.
     * Throws a ValueError if the data stack is empty.
     *
     * @return mixed The popped data.
     */
    public function popData(): mixed
    {
        if (empty($this->dataStack)) {throw new ValueError;}
        return array_pop($this->dataStack);
    }

    /**
     * Pushes a call onto the call stack.
     *
     * @param mixed $call The call to push.
     */
    public function pushCall(mixed $call): void 
    {
        array_push($this->callStack, $call);
    }

    /**
    * Pops a call from the call stack.
    * Throws a VariableAccessError if the call stack is empty.
    *
    * @return mixed The popped call.
    */
    public function popCall(): mixed
    {
        if (empty($this->callStack)) {throw new VariableAccessError;}
        return array_pop($this->callStack);
    }

    /**
     * Pushes the temporary frame onto the frame stack.
     * Throws a FrameAccessError if the temporary frame is null.
     */
    public function pushFrame(): void
    {
        if ($this->TF === null) {throw new FrameAccessError;}
        array_push($this->frameStack, $this->TF);
        $this->TF = null;
    }

    /**
     * Pops a frame from the frame stack and sets it as the temporary frame.
     * Throws a FrameAccessError if the frame stack is empty or the popped frame is null.
     */
    public function popFrame(): void
    {
        if (empty($this->frameStack[1]) ) {throw new FrameAccessError;}
        
        $this->TF = array_pop($this->frameStack);
        
        if ($this->TF == null) {throw new FrameAccessError;}
    }
    
    /**
     * Returns the local frame.
     * Throws a FrameAccessError if the frame stack is empty.
     *
     * @return Frame The local frame.
     */
    public function LF(): Frame
    {
        if (!end($this->frameStack)) {throw new FrameAccessError;}
        return end($this->frameStack);
    }

    /**
     * Returns the global frame.
     * Throws a FrameAccessError if the global frame is null.
     *
     * @return Frame The global frame.
     */
    public function GF(): Frame
    {
        if ($this->GF == null) {throw new FrameAccessError;}
        return $this->GF;
    }

    /**
     * Returns the temporary frame.
     * Throws a FrameAccessError if the temporary frame is null.
     *
     * @return Frame The temporary frame.
     */
    public function TF(): Frame
    {
        if ($this->TF == null) {throw new FrameAccessError;}
        return $this->TF;
    }

    /**
     * Creates a new temporary frame.
     */
    public function createTempFrame(): void
    {
        $this->TF = new Frame();
    }

    /**
     * Calls a label.
     * Pushes the current instruction pointer plus one onto the call stack and sets the instruction pointer to the label.
     * Throws a SemanticError if the label does not exist.
     *
     * @param int|string $label The label to call.
     */
    public function callLabel(int|string $label): void
    {
        if (!$this->labelExists($label)) {throw new SemanticError;}
       
        $this->pushCall($this->instructionPtr + 1);
        $this->instructionPtr = $this->labelMap[$label];
    }

    /**
     * Returns from a call.
     * Pops the call stack and sets the instruction pointer to the popped value.
     * Throws a VariableAccessError if the call stack is empty.
     */
    public function returnCall(): void
    {
        if (empty($this->callStack)) {throw new VariableAccessError;}
        $this->instructionPtr = array_pop($this->callStack);
    }

    /**
     * Checks if a label exists.
     *
     * @param int|string|null $label The label to check.
     * @return bool True if the label exists, false otherwise.
     */
    public function labelExists(int|string|null $label): bool
    {
        if ($label == null) {return false;}
        return array_key_exists($label, $this->labelMap);
    }

    /**
     * Adds a label to the label map.
     * Throws a SemanticError if the label already exists.
     *
     * @param int|string|null $label The label to add.
     * @param int $instructionPtr The instruction pointer.
     */
    public function addLabel(int|string|null $label, int $instructionPtr): void
    {
        if ($this->labelExists($label)) {throw new SemanticError;}
        $this->labelMap[$label] = $instructionPtr;
    }

    /**
     * Gets the instruction pointer.
     *
     * @return int|null The instruction pointer.
     */
    public function getInstructionPtr(): ?int
    {
        return $this->instructionPtr;
    }

    /**
     * Checks if the root of the XML document is valid.
     *
     * @param string $xmlString The XML string to check.
     * @param string $expectedRoot The expected root node name.
     * @return bool True if the root node name matches the expected root, false otherwise.
     * @throws SourceStructureError If the root node is null.
     */
    public function hasValidRoot(string $xmlString, string $expectedRoot = 'program'): bool
    {
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xmlString);
        $root = $doc->documentElement;
        if ($root === null) {throw new SourceStructureError;}
        return $root->nodeName === $expectedRoot;
    }

    /**
     * Loads the instructions from the DOM document.
     * Throws a SourceStructureError if the DOM document is not valid.
     *
     * @param mixed $domDocument The DOM document.
     */
    public function loadInstructions(mixed $domDocument): void
    {       
        // Check if the input is a DOMDocument instance, if not throw an error
        if (!$domDocument instanceof \DOMDocument) {throw new SourceStructureError;}
        
        // Check if the XML document has a valid root, if not throw an error
        else if (!$domDocument->saveXML() || !$this->hasValidRoot($domDocument->saveXML())) 
        {throw new SourceStructureError;}

        // Get the root of the XML document
        $root = $domDocument->documentElement;
        if ($root === null) {throw new SourceStructureError;}
        
        // Check if all child nodes of the root are 'instruction' elements, if not throw an error
        foreach ($root->childNodes as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE && $childNode->nodeName !== 'instruction') 
            {throw new SourceStructureError;}
        }
        
        // Get all 'instruction' elements from the XML document
        $instructions = $domDocument->getElementsByTagName('instruction');      
        foreach ($instructions as $instruction) {
            
            // Get the 'order' and 'opcode' attributes of the instruction
            $order = $instruction->getAttribute('order');
            $opcode = strtoupper($instruction->getAttribute('opcode')); 
            
            // Check if the 'order' and 'opcode' attributes are valid, if not throw an error
            if ($order === '' || $opcode === '' || !is_numeric($order) || $order < 1) 
            {throw new SourceStructureError;}
            
            // Get all child elements of the instruction
            $parametersNodeList = $instruction->getElementsByTagName('*');
            $parameters = array();
            
            // Check if an instruction with the same order already exists, if so throw an error
            if (isset($this->instructionMap[$order])) {throw new SourceStructureError;}
            
            // Process each child element of the instruction
            $args = [];
            foreach ($parametersNodeList as $paramNode) {
                
                $argName = $paramNode->nodeName;
                if (preg_match('/arg[1-3]/', $argName)) {
                    
                    if ($paramNode->nodeValue === null) {throw new SourceStructureError;}
                    $args[$argName] = [
                        'value' => trim($paramNode->nodeValue),
                        'type' => $paramNode->getAttribute('type')
                    ];
                    
                    // Check if the value of an 'int' argument is a valid number, if not throw an error
                    if (($args[$argName]['type'] === 'int') && (!is_numeric($args[$argName]['value']))) {                       
                        throw new SourceStructureError;                                         
                    }
                }
            }
            
            // Check if the arguments are in the correct order, if not throw an error
            if ((isset($args['arg2']) && !isset($args['arg1'])) || 
            (isset($args['arg3']) && (!isset($args['arg2']) || !isset($args['arg1'])))) {
                throw new SourceStructureError;
            }
            
            // Sort the arguments by their names and remove the names
            ksort($args);
            $parameters = array_values($args);
            
            // Create a new Instruction instance and add it to the instruction map
            $order = (int)$order;
            $this->instructionMap[$order] = new Instruction($opcode, $parameters, $this);
            if ($order > $this->lastInstructionOrder) {
                $this->lastInstructionOrder = $order;
            }

            // If the opcode is 'LABEL', add the label to the label map
            if ($opcode === 'LABEL') {           
                $this->addLabel($parameters[0]['value'], $order);
            }
        }
        
        // Sort the instruction map by the order of the instructions
        ksort($this->instructionMap);
    }

    /**
     * Executes the instructions.
     */
    public function execute(): void
    {
        // Push the global frame onto the frame stack at the beginning of execution
        array_push($this->frameStack, $this->GF);
        
        // Loop through all instructions in the order they appear in the instruction map
        while ($this->instructionPtr <= $this->lastInstructionOrder) {
            
            // If an instruction does not exist at the current instruction pointer, 
            // increment the instruction pointer and continue to the next iteration
            if (!isset($this->instructionMap[$this->instructionPtr])) {
                $this->instructionPtr++;
                continue;
            }
            
            // Get the instruction at the current instruction pointer and execute it
            $instruction = $this->instructionMap[$this->instructionPtr];
            $instruction->execute();
            $this->instructionPtr++;
        }
    }
}

/**
 * The Instruction class represents an instruction in the IPP24code language.
 * It contains the opcode, parameters, and executor of the instruction, 
 * and provides methods to execute the instruction and get variable values and types.
 */
class Instruction
{
    // The opcode of the instruction
    private string $opcode;
    // The parameters of the instruction
    /** @var array<int, array<string, string>> */
    private array $parameters;
    private Executor $executor;

    /**
     * Constructs a new Instruction instance.
     *
     * @param string $opcode The opcode of the instruction.
     * @param array<int, array<string, string>> $parameters The parameters of the instruction.
     * @param Executor $executor The executor instance.
     */
    public function __construct(string $opcode, array $parameters, Executor $executor)
    {
        $this->opcode = $opcode;
        $this->parameters = $parameters;
        $this->executor = $executor;
    }

    /**
     * Executes the instruction.
     * Calls the method with the same name as the opcode.
     * Throws a SourceStructureError if the method does not exist.
     * Throws an InternalErrorException if the method call fails.
     */
    public function execute(): void 
    {
        if (!method_exists($this, $this->opcode)) {throw new SourceStructureError;}
        $this->{$this->opcode}();
    }

    /**
     * Gets the value of a variable.
     * Throws a VariableAccessError if the variable does not exist.
     *
     * @param string $var The name of the variable.
     * @return mixed The value of the variable.
     */
    private function getVarValue(string $var): mixed
    {
        list($frame, $var) = explode('@', $var);
        $frame = $this->getFrame($frame);   
        $var = $frame->getVar($var);
        if ($var === null) {throw new VariableAccessError;}
        return $var->getVal();
    }

    /**
     * Gets the type of a variable.
     * Throws a VariableAccessError if the variable does not exist.
     *
     * @param string $var The name of the variable.
     * @return string|null The type of the variable.
     */
    private function getVarType(string $var): ?string
    {
        list($frame, $var) = explode('@', $var);
        $frame = $this->getFrame($frame);
        $var = $frame->getVar($var);
        if ($var === null) {throw new VariableAccessError;}
        return $var->getType();
    }

    /**
     * Gets a frame by its name.
     * Throws a FrameAccessError if the frame does not exist.
     *
     * @param string $frame The name of the frame.
     * @return Frame The frame.
     */
    private function getFrame(string $frame): Frame
    {
        switch ($frame) {
            case 'TF':
                return $this->executor->TF();
            case 'LF':
                return $this->executor->LF();
            case 'GF':
                return $this->executor->GF();
            default:
                throw new FrameAccessError;
        }
    }
    
    /**
     * Checks if the number of parameters is correct.
     *
     * @param int $expectedCount The expected number of parameters.
     */
    private function checkParameterCount(int $expectedCount): void {
        if (count($this->parameters) !== $expectedCount) {
            throw new SourceStructureError;
        }
    }

    /**
     * Gets a variable by its name.
     * Throws a VariableAccessError if the variable does not exist.
     *
     * @param string $var The name of the variable.
     * @return Variable The variable.
     */
    private function getVariable(string $var): Variable {
        list($frame, $varName) = explode('@', $var);
        $frame = $this->getFrame($frame);
        $var = $frame->getVar($varName);
        if ($var === null) {
            throw new VariableAccessError;
        }
        return $var;
    }


    public function MOVE(): void 
    {
        $this->checkParameterCount(2);
        
        // Destructure the parameters array into two variables, $dest and $src.
        list($dest, $src) = $this->parameters;

        $destVar = $this->getVariable($dest['value']);
    
        if ($src['type'] === 'var') {
            // Source is a variable
            $srcVar = $this->getVariable($src['value']);

            $destVar->setVal($srcVar->getVal());
            $destVar->setType($srcVar->getType());
        } else {
            // Source is a symbol
            $destVar->setVal($src['value']);
            $destVar->setType($src['type']); 
        }

    }

    public function CREATEFRAME(): void 
    {
        $this->checkParameterCount(0);
        $this->executor->createTempFrame();
    }

    public function PUSHFRAME(): void 
    {
        $this->checkParameterCount(0);
        $this->executor->pushFrame();
    }

    public function POPFRAME(): void 
    {
        $this->checkParameterCount(0);
        $this->executor->popFrame();
    }
    
    public function DEFVAR(): void 
    {
        $this->checkParameterCount(1);
        
        // Split the value of the first parameter into two parts, 
        // $frame and $varName, using the '@' character as the delimiter.
        list($frame, $varName) = explode('@', $this->parameters[0]['value']);
        
        $frame = $this->getFrame($frame);
        
        // Check if the variable named $varName already exists in the frame. 
        // If it does, a semantic error is thrown.
        if ($frame->getVar($varName) !== null) {throw new SemanticError;}

        $frame->defVar($varName);
    }

    public function CALL(): void 
    {
        $this->checkParameterCount(1);
        list($label) = $this->parameters;

        $this->executor->callLabel($label['value']);
    }

    public function RETURN(): void 
    {
        $this->checkParameterCount(0);
        $this->executor->returnCall();
    }

    public function PUSHS(): void 
    {
        $this->checkParameterCount(1);
        list($var) = $this->parameters;
        
        if ($var['type'] === 'var') {
            // If the parameter is a variable, get the value and type of the variable.
            $value['value'] = $this->getVarValue($var['value']);
            $value['type'] = $this->getVarType($var['value']);
            $this->executor->pushData($value);
        } else {
            // If the parameter is not a variable, push it onto the data stack.
            $this->executor->pushData($var);
        }
    }

    public function POPS(): void 
    {
        $this->checkParameterCount(1);       
        list($var) = $this->parameters;
        
        $value = $this->executor->popData();  
        
        // Check if the popped value is an array and contains 'value' and 'type' keys. 
        if (!is_array($value) || !isset($value['value']) || !isset($value['type'])) {
            throw new OperandTypeError;
        }
        
        $var = $this->getVariable($var['value']);   
        $var->setVal($value['value']);
        $var->setType($value['type']);
    }

    public function performArithmeticOperation(mixed $operation): void 
    {
        $this->checkParameterCount(3);
        list($dest, $src1, $src2) = $this->parameters;    
        $destVar = $this->getVariable($dest['value']);

        // Check if the first and second parameters are variables.
        // If they are, get their values. If they're not, use their 'value' directly.
        $src1Val = $src1['type'] === 'var' ? $this->getVarValue($src1['value']) : $src1['value'];
        $src2Val = $src2['type'] === 'var' ? $this->getVarValue($src2['value']) : $src2['value'];
        
        // Check if the values of the first and second parameters are numeric. 
        if (!is_numeric($src1Val) || !is_numeric($src2Val)) {throw new OperandTypeError;}
    
        switch ($operation) {
            case 'ADD':
                $result = $src1Val + $src2Val;
                break;
            case 'SUB':
                $result = $src1Val - $src2Val;
                break;
            case 'MUL':
                $result = $src1Val * $src2Val;
                break;
            case 'IDIV':
                if ($src2Val == 0) {throw new OperandValueError;}
                $result = $src1Val / $src2Val;
                break;
            default:
                throw new InternalErrorException;
        }
        
        $destVar->setVal($result);
        $destVar->setType('int');
    }
    
    public function ADD(): void
    {
        $this->performArithmeticOperation('ADD');
    }
    
    public function SUB(): void
    {
        $this->performArithmeticOperation('SUB');
    }
    
    public function MUL(): void
    {
        $this->performArithmeticOperation('MUL');
    }
    
    public function IDIV(): void
    {
        $this->performArithmeticOperation('IDIV');
    }

    public function LT(): void
    {
        $this->compare(function($a, $b) { return $a < $b; });
    }
    
    public function GT(): void
    {
        $this->compare(function($a, $b) { return $a > $b; });
    }
    
    public function EQ(): void
    {
        $this->compare(function($a, $b) { return $a == $b; });
    }
    
    private function compare(callable $comparator): void
    {
        $this->checkParameterCount(3);
        list($dest, $src1, $src2) = $this->parameters;

        $src1Var = $this->getVariable($src1['value']);
        $src2Var = $this->getVariable($src2['value']);
        
        // Check if the types of the two source variables are the same. 
        if ($src1Var->getType() !== $src2Var->getType()) {throw new OperandValueError;}

        $result = $comparator($src1Var->getVal(), $src2Var->getVal());

        $destVar = $this->getVariable($dest['value']);
        $destVar->setVal($result);
        $destVar->setType('bool');
    }

    public function AND(): void
    {
        $this->booleanOperation(function($a, $b) { return $a && $b; });
    }
    
    public function OR(): void
    {
        $this->booleanOperation(function($a, $b) { return $a || $b; });
    }
    
    public function NOT(): void
    {
        $this->checkParameterCount(2);
        list($dest, $src1) = $this->parameters;
        
        $src1Var = $this->getVariable($src1['value']);
        if ($src1Var->getType() !== 'bool') {throw new OperandValueError;}
        
        // Perform a logical NOT operation on the value of the source variable.
        $result = !$src1Var->getVal();
        
        $destVar = $this->getVariable($dest['value']);
        $destVar->setVal($result);
        $destVar->setType('bool');
    }
    
    private function booleanOperation(callable $operation): void
    {
        $this->checkParameterCount(3);
        list($dest, $src1, $src2) = $this->parameters;

        $src1Var = $this->getVariable($src1['value']);
        $src2Var = $this->getVariable($src2['value']);
        if ($src1Var->getType() !== 'bool' || $src2Var->getType() !== 'bool') 
        {throw new OperandValueError;}
        
        // Call the operation function with the values of the two source variables as arguments.
        // The operation function should return a boolean result.
        $result = $operation($src1Var->getVal(), $src2Var->getVal());

        $destVar = $this->getVariable($dest['value']);
        $destVar->setVal($result);
        $destVar->setType('bool');
    }

    public function INT2CHAR(): void
    {
        $this->checkParameterCount(2);
        list($dest, $src) = $this->parameters;

        $srcVar = $this->getVariable($src['value']);
        if ($srcVar->getType() !== 'int') {throw new OperandValueError;}       
        if (!is_int($srcVar->getVal())) {throw new OperandTypeError;}

        // Check if the value of the source variable is within the range of valid Unicode code points.
        if ($srcVar->getVal() < 0 || $srcVar->getVal() > 1114111) {throw new StringOperationError;}

        // Convert the value of the source variable to a Unicode character.
        $char = mb_chr($srcVar->getVal(), 'UTF-8');

        $destVar = $this->getVariable($dest['value']);
        $destVar->setVal($char);
        $destVar->setType('string');
    }
    
    public function STRI2INT(): void
    {
        $this->checkParameterCount(3);
    
        list($dest, $src1, $src2) = $this->parameters;
    
        $src1Var = $this->getVariable($src1['value']);
        $src2Var = $this->getVariable($src2['value']);

        if ($src1Var->getType() !== 'string' || $src2Var->getType() !== 'int') 
        {throw new OperandValueError;}
    
        $src1Val = $src1Var->getVal();
        $src2Val = $src2Var->getVal();
    
        // Check if the value of the first source variable is a string 
        // and the value of the second source variable is an integer. 
        if (!is_string($src1Val) || !is_int($src2Val)) 
        {throw new OperandTypeError;}
    
        // Check if the value of the second source variable is within the range of valid indices for the string. 
        if ($src2Val < 0 || $src2Val >= mb_strlen($src1Val, 'UTF-8')) 
        {throw new ValueError;}
    
        // Get the character at the index specified by the value 
        // of the second source variable in the string specified by the value of the first source variable.
        $char = mb_substr($src1Val, $src2Val, 1, 'UTF-8');
    
        // Convert the character to its Unicode code point.
        $ord = mb_ord($char, 'UTF-8');
    
        $destVar = $this->getVariable($dest['value']);
        $destVar->setVal($ord);
        $destVar->setType('int');
    }

    public function READ(): void
    {
        $this->checkParameterCount(2);
        list($dest, $type) = $this->parameters;
        
        $destVar = $this->getVariable($dest['value']);
        
        switch ($type['value']) {
            case 'int':
                $input = $this->executor->interpret->readInt();
                break;
            case 'string':
                $input = $this->executor->interpret->readString();
                break;
            case 'bool':
                $input = $this->executor->interpret->readBool();
                break;
            default:         
                throw new ValueError;
        }
    
        if ($input === null) {
            $destVar->setVal('nil');
            $destVar->setType('nil');
        } else {
            $destVar->setVal($input);
            $destVar->setType($type['value']);
        }
    }

    public function WRITE(): void
    {
        $this->checkParameterCount(1);
        list($src) = $this->parameters;
        
        // Check if the parameter is a variable.
        // If it is, get its value and type. If it's not, use its 'value' and 'type' directly.
        $value = $src['type'] === 'var' ? $this->getVarValue($src['value']) : $src['value'];
        $type = $src['type'] === 'var' ? $this->getVarType($src['value']) : $src['type'];
        
        switch ($type) {
            case 'int':
                
                if (!is_numeric($value)) {throw new OperandTypeError;}
                
                $this->executor->interpret->writeInt(intval($value));
                break;
            case 'string': 
                
                if (!is_string($value)) {throw new OperandTypeError;}
                
                // Replace all escape sequences in the string with their corresponding characters.
                $value = preg_replace_callback('/\\\\(\d{3})/', function ($matches) {
                    return chr((int)$matches[1]);
                }, $value);           
                if ($value === null) {throw new OperandTypeError;}
                
                $this->executor->interpret->writeString($value);
                break;
            case 'bool':
                
                $this->executor->interpret->writeBool((bool)$value);
                break;
            case 'float':
                
                if (!is_numeric($value)) {throw new OperandTypeError;}
                
                $this->executor->interpret->writeFloat(floatval($value));
                break;
            case 'nil':
                
                $this->executor->interpret->writeString('');
                break;
            default:             
                
                throw new ValueError;
        }
    }

    public function CONCAT(): void
    {
        $this->checkParameterCount(3);
        list($dest, $src1, $src2) = $this->parameters;
        
        $destVar = $this->getVariable($dest['value']);
        
        $src1Value = $src1['type'] === 'var' ? $this->getVarValue($src1['value']) : $src1['value'];
        $src2Value = $src2['type'] === 'var' ? $this->getVarValue($src2['value']) : $src2['value'];
        
        if (!is_string($src1Value) || !is_string($src2Value)) {throw new OperandValueError;}
        
        // Concatenate the values of the two source variables.
        $concatenated = $src1Value . $src2Value;
        $destVar->setVal($concatenated);
        $destVar->setType('string');
    }

    public function STRLEN(): void
    {
        $this->checkParameterCount(2);
        list($dest, $src) = $this->parameters;
        
        $destVar = $this->getVariable($dest['value']);
        
        $value = $src['type'] === 'var' ? $this->getVarValue($src['value']) : $src['value'];
        
        if (!is_string($value)) {throw new OperandValueError;}
        
        // Get the length of the string.
        $length = mb_strlen($value, 'UTF-8'); 
        $destVar->setVal($length);
        $destVar->setType('int');
    }

    public function GETCHAR(): void
    {
        $this->checkParameterCount(3);
        list($dest, $src, $index) = $this->parameters;
    
        $destVar = $this->getVariable($dest['value']);
    
        // Check if the source and index parameters are variables.
        // If they are, get their values and types. If they're not, use their 'value' and 'type' directly.
        $srcValue = $src['type'] === 'var' ? $this->getVarValue($src['value']) : $src['value'];
        $srcType = $src['type'] === 'var' ? $this->getVarType($src['value']) : $src['type'];
        $indexValue = $index['type'] === 'var' ? $this->getVarValue($index['value']) : $index['value'];
        $indexType = $index['type'] === 'var' ? $this->getVarType($index['value']) : $index['type'];
        
        // Check if the type of the source parameter is 'string' and the type of the index parameter is 'int'.
        if ($srcType !== 'string' || $indexType !== 'int') {throw new OperandValueError;}
        
        // Check if the value of the source parameter is a string and the value of the index parameter is numeric.
        if (!is_string($srcValue) || !is_numeric($indexValue)) {throw new OperandTypeError;}
        
        // Convert the value of the index parameter to an integer.
        $indexValue = intval($indexValue); 
        
        // Check if the value of the index parameter is within the range of valid indices for the string.
        if ($indexValue < 0 || $indexValue >= mb_strlen($srcValue, 'UTF-8')) {throw new StringOperationError;}
        
        // Get the character at the index specified by the value of the index parameter 
        // in the string specified by the value of the source parameter.
        $char = mb_substr($srcValue, $indexValue, 1, 'UTF-8');
        $destVar->setVal($char);
        $destVar->setType('string');
    }
    
    public function SETCHAR(): void
    {
        $this->checkParameterCount(3);
        list($dest, $index, $src) = $this->parameters;

        $destVar = $this->getVariable($dest['value']);
        $destValue = $this->getVarValue($dest['value']);
        $indexValue = $index['type'] === 'var' ? $this->getVarValue($index['value']) : $index['value'];
        $srcValue = $src['type'] === 'var' ? $this->getVarValue($src['value']) : $src['value'];

        if (!is_string($destValue) || !is_int($indexValue) || !is_string($srcValue)) {throw new OperandValueError;}

        // Check if the value of the index parameter is within the range of valid indices for the string.
        if ($indexValue < 0 || $indexValue >= mb_strlen($destValue, 'UTF-8')) {throw new ValueError;}

        // Get the first character of the source value.
        $char = mb_substr($srcValue, 0, 1, 'UTF-8');

        // Replace the character at the index specified by the value of the index parameter 
        // in the string specified by the value of the destination parameter with the character.
        $destVar->setVal(mb_substr($destValue, 0, $indexValue, 'UTF-8') . $char . 
        mb_substr($destValue, $indexValue + 1, mb_strlen($destValue, 'UTF-8'), 'UTF-8'));
    }

    public function TYPE(): void
    {
        $this->checkParameterCount(2);
        list($dest, $src) = $this->parameters;
        
        $destVar = $this->getVariable($dest['value']);
        
        // Check if the source parameter is a variable.
        if ($src['type'] === 'var') {
            // If it is, get the variable referenced by $src['value'].
            $srcVar = $this->getVariable($src['value']);
            
            // If the value of the source variable is null, set $srcType to an empty string.
            // Otherwise, set $srcType to the type of the source variable.
            if ($srcVar->getVal() === null ) {
                $srcType = '';
            } else {
                $srcType = $srcVar->getType();
            }
        } else {
            $srcType = $src['type'];
        }
    
        $destVar->setVal($srcType);
        $destVar->setType('string');
    }

    public function LABEL(): void
    {
        $this->checkParameterCount(1);       
    }

    public function JUMP(): void
    {
        $this->checkParameterCount(1);     
        $this->executor->callLabel($this->parameters[0]['value']);
    }

    public function JUMPIFEQ(): void
    {
        $this->jumpIf(function($symb1Val, $symb2Val) {
            return $symb1Val == $symb2Val;
        });
    }
    
    public function JUMPIFNEQ(): void
    {
        $this->jumpIf(function($symb1Val, $symb2Val) {
            return $symb1Val !== $symb2Val;
        });
    }
    
    private function jumpIf(callable $comparison): void
    {
        $this->checkParameterCount(3);
        list($label, $symb1, $symb2) = $this->parameters;
    
        // Check if the first and second parameters are variables.
        // If they are, get their values and types. If they're not, use their 'value' and 'type' directly.
        $symb1Val = $symb1['type'] === 'var' ? $this->getVarValue($symb1['value']) : $symb1['value'];
        $symb1Type = $symb1['type'] === 'var' ? $this->getVarType($symb1['value']) : $symb1['type'];
        $symb2Val = $symb2['type'] === 'var' ? $this->getVarValue($symb2['value']) : $symb2['value'];
        $symb2Type = $symb2['type'] === 'var' ? $this->getVarType($symb2['value']) : $symb2['type'];
        
        // Check if the types of the first and second parameters are the same, unless either of them is 'nil'.
        // If they're not the same and neither of them is 'nil', an exception is thrown.
        if (($symb1Type !== $symb2Type && $symb1Type !== 'nil' && $symb2Type !== 'nil')) 
        {throw new OperandValueError;}
        
        // Call the comparison function with the values of the first and second parameters as arguments.
        if ($comparison($symb1Val, $symb2Val)) {
            // If the result of the comparison is true, call the label specified by $label['value'].
            $this->executor->callLabel($label['value']);
        }
    }

    public function EXIT(): void
    {
        $this->checkParameterCount(1);
        list($symb) = $this->parameters;
    
        $symbVal = $symb['type'] === 'var' ? $this->getVarValue($symb['value']) : $symb['value'];
        $symbType = $symb['type'] === 'var' ? $this->getVarType($symb['value']) : $symb['type'];
    
        if ($symbType !== 'int' || $symbVal < 0 || $symbVal > 9) {throw new OperandValueError;}
        
        exit($symbVal);
    }
    
    public function DPRINT(): void
    {
        $this->checkParameterCount(1);
        list($symb) = $this->parameters;
    
        $symbVal = $symb['type'] === 'var' ? $this->getVarValue($symb['value']) : $symb['value'];

        if(!is_string($symbVal)) {throw new OperandTypeError;}
    
        fwrite(STDERR, $symbVal);
    }
    
    public function BREAK(): void 
    {
        $this->checkParameterCount(0);
        fwrite(STDERR, "Current instruction pointer: " . $this->executor->getInstructionPtr() . "\n");
    }
}