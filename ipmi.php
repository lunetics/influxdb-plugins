<?php

$ipmitoolRawData = file_get_contents('php://stdin');

$influxIpmi = new InfluxIpmi($ipmitoolRawData);

echo $influxIpmi;


class InfluxIpmi
{

    const MEASUREMENT = 'ipmi';

    private static $ipmiArrayValues = [
        'sensor',
        'value',
        'units',
        'state',
        'low_non_recoverable',
        'low_critical',
        'low_non_critical',
        'up_non_critical',
        'up_critical',
        'up_noncritical'
    ];

    private $influxIpmiRaw;

    public function __construct($ipmitoolRawData)
    {
        $this->influxIpmiRaw = $ipmitoolRawData;
    }

    public function __toString()
    {
        $data = $this->__toArray();
        $lines = $this->processData($data);
        $string = '';
        foreach ($lines as $line) {
            $string .= sprintf("%s,%s %d\n", self::MEASUREMENT, $line, time());
        }

        return $string;

    }

    public function __toArray()
    {
        $data = [];
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->influxIpmiRaw) as $line) {
            $lineArray = preg_split("/\s*\|\s*/", $line);
            $lineArray = array_combine(self::$ipmiArrayValues, $lineArray);

            if ($lineArray['units'] == "Volts" && $lineArray['state'] != "na") {
                $data['voltage'][] = $lineArray;
            }
            if ($lineArray['units'] == "RPM" && $lineArray['state'] != "na") {
                $data['fan'][] = $lineArray;
            }
            if ($lineArray['units'] == "degrees C" && $lineArray['state'] != "na") {
                $data['temperature'][] = $lineArray;
            }

            if ($lineArray['units'] == "Watts" && $lineArray['state'] != "na") {
                $data['power'][] = $lineArray;
            }
            if ($lineArray['units'] == "Amps" && $lineArray['state'] != "na") {
                $data['current'][] = $lineArray;
            }

        }

        return $data;
    }

    private function processData(array $data)
    {
        foreach ($data as $type => $sensorList) {
            foreach ($sensorList as $sensor) {
                $sensorLines[] = $this->processField($sensor);
            }
            $lines[] = sprintf('type=%s,%s', $type, implode(',', $sensorLines));
            unset($sensorLines);
        }

        return $lines;
    }

    private function processField(array $sensor)
    {
        $sensorId = preg_replace('/( |,)/', '\\\${0}', $sensor['sensor']);
        $sensorValue = $sensor['value'];

        return sprintf('%s=%s', $sensorId, $sensorValue);
    }
}


