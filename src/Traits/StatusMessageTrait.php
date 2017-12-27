<?php

namespace Urbics\Laracivi\Traits;

trait StatusMessageTrait
{
    protected $defaultFailStatus = 404;
    protected $defaultSuccessStatus = 200;
    protected $resultStructure = array('status_message' => '', 'status_code' => 200);

    public function getResultStructure(array $options = null)
    {
        $result = $this->resultStructure;
        if ($options && is_array($options)) {
            foreach ($options as $key => $value) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function getSuccessStatusCode()
    {
        return $this->defaultSuccessStatus;
    }

    public function getFailStatusCode()
    {
        return $this->defaultFailStatus;
    }

    public function getStatusMessage()
    {
        return $this->resultStructure['status_message'];
    }

    public function getStatusCode()
    {
        return $this->resultStructure['status_code'];
    }

    public function isSuccess($result)
    {
        return $result['status_code'] == $this->defaultSuccessStatus;
    }

    public function isFailure($result)
    {
        return $result['status_code'] != $this->defaultSuccessStatus;
    }

    public function succeeded($result = null)
    {
        if ($result) {
            return $result['status_code'] == $this->defaultSuccessStatus;
        }

        return $this->resultStructure['status_code'] == $this->defaultSuccessStatus;
    }

    public function failed($result = null)
    {
        if ($result) {
            return $result['status_code'] != $this->defaultSuccessStatus;
        }
        return $this->resultStructure['status_code'] != $this->defaultSuccessStatus;
    }

    public function getSuccessResult($message = 'Success.')
    {
        $result['status_code'] = $this->defaultSuccessStatus;
        $result['status_message'] = $message;
        return $result;
    }

    public function getFailureResult($message = 'Fail.')
    {
        $result['status_code'] = $this->defaultFailStatus;
        $result['status_message'] = $message;
        return $result;
    }

    public function successResult($message = 'Success.')
    {
        $this->resultStructure['status_code'] = $this->defaultSuccessStatus;
        $this->resultStructure['status_message'] = $message;
        return $this;
    }

    public function failureResult($message = 'Fail.')
    {
        $this->resultStructure['status_code'] = $this->defaultFailStatus;
        $this->resultStructure['status_message'] = $message;
        return $this;
    }
}
