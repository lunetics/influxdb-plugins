<?php
/**
 * Reads 'ipmitool sensor' output from stream and converts it into influxdb line protocol
 *
 * usage example:
 *      php ipmitool.php < ipmitool sensor
 *   or with custom hostname
 *      php ipmitool.php < ipmitool sensor $(hostname -fqd)
 *   or use a cronjob for faster execution:
 *      php ipmitool.php < /tmp/ipmidata.txt
 *
 */

$ipmitoolRawData = file_get_contents('php://stdin');

if (!strlen($ipmitoolRawData)) {
    echo "No Input given!";
    die();
}

if($argc = 2 && strlen($argv[1]) >= 1) {
    $hostname = $argv[1];
}

$influxIpmi = new InfluxIpmi($ipmitoolRawData, $hostname);

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

    public function __construct($ipmitoolRawData, $hostname = null)
    {
        $this->hostname = $hostname;
        if (null === $hostname || '' === $hostname) {
            $this->hostname = gethostname();
        }
        $this->influxIpmiRaw = $ipmitoolRawData;
    }

    public function __toString()
    {
        $data = $this->__toArray();
        $lines = $this->processData($data);
        $string = '';
        foreach ($lines as $line) {
            $string .= sprintf("%s,%s\n", self::MEASUREMENT, $line);
        }

        return $string;

    }

    public function __toArray()
    {
        $data = [];
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->influxIpmiRaw) as $line) {
            $lineArray = preg_split("/\s*\|\s*/", $line);
            if (count($lineArray) !== 10) {
                continue;
            }
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
        $lines = [];
        $host = $this->processField('host', $this->hostname);
        foreach ($data as $type => $sensorList) {
            foreach ($sensorList as $sensor) {
                $sensorLines[] = $this->processField('value', (float)$sensor['value']);

                $sensorTags[] = $host;
                $sensorTags[] = $this->processField('type', $type);
                $sensorTags[] = $this->processField('instance', $sensor['sensor']);

                $lines[] = sprintf('%s %s', implode(',', $sensorTags), implode(',', $sensorLines));
                unset($sensorLines);
                unset($sensorTags);
            }
        }

        return $lines;
    }

    /**
     * @param string $fieldName
     * @param string $value
     *
     * @return string
     */
    private function processField($fieldName, $value)
    {
        $fieldName = preg_replace('/( |,)/', '\\\${0}', $fieldName);
        $value = preg_replace('/( |,)/', '\\\${0}', $value);

        return sprintf('%s=%s', $fieldName, $value);
    }
}


