<?php
/**
 * Gather additional statistic on cpu's for freebsd.
 * Currently supported:
 *  * freq
 *  * temperature
 *
 * e.g. php cpu.php < sysctl -iq dev.cpu |grep -v %
 */

$sysctlRawData = file_get_contents('php://stdin');
$cpuInfo = new InfluxFreebsdCpu($sysctlRawData);

echo $cpuInfo;

class InfluxFreebsdCpu
{
    /**
     * measurment tag
     */
    const MEASUREMENT = 'cpu';

    /**
     * @var string
     */
    private $hostname;

    /**
     * @var string
     */
    private $sysctlCpuInfo;

    /**
     * InfluxFreebsdCpu constructor.
     *
     * @param string      $sysctlCpuInfo Output of sysctl values (`sysctl a dev.cpu`)
     * @param null|string $hostname      Override default hostname
     */
    public function __construct($sysctlCpuInfo, $hostname = null)
    {
        $this->hostname = $hostname;
        if (null === $hostname || '' === $hostname) {
            $this->hostname = gethostname();
        }

        $this->sysctlCpuInfo = $sysctlCpuInfo;
    }

    /**
     * @return string
     */
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

    /**
     * @return array
     */
    public function __toArray()
    {
        $data = [];
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->sysctlCpuInfo) as $line) {
            if(strpos($line, 'dev.cpu') === false) {
                continue;
            }
            $line = preg_replace('/dev.cpu.(\d+)(.*)/', 'cpu${1}${2}', $line);
            $data = array_merge_recursive($this->stringToArray($line), $data);
        }

        return $data;
    }

    /**
     * Parses an
     *
     * @param string $path
     * @param string $value
     * @param string $separator
     *
     * @return array
     */
    private function stringToArray($path, $value = '', $separator = '.')
    {
        if ('' === $value) {
            list($path, $value) = explode(':', $path);
        }

        $pos = strpos($path, $separator);

        if ($pos === false) {

            return array($path => trim($value));
        }

        $key = substr($path, 0, $pos);
        $path = substr($path, $pos + strlen($separator));

        $result = array(
            $key => $this->stringToArray($path, $value),
        );

        return $result;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function processData(array $data)
    {
        $lines = [];

        // Set host
        $host = $this->processField('host', $this->hostname);

        // extract frequency
        foreach ($data as $cpu => $values) {
            if (isset($values['freq'])) {
                $frequency = $values['freq'];
            }
        }

        foreach ($data as $cpu => $values) {

            if (isset($values['temperature'])) {
                $type = 'temperature';
                $cpuDataFields[] = $this->processField($type, (float)$values['temperature']);
            }

            if (null !== $frequency) {
                $type = 'frequency';
                $cpuDataFields[] = $this->processField($type, (int)$frequency.'i');
            }

            $cpuDataTags[] = $host;
            $cpuDataTags[] = $this->processField('cpu', $cpu);

            $lines[] = sprintf('%s %s', implode(',', $cpuDataTags), implode(',', $cpuDataFields));

            unset($cpuDataFields);
            unset($cpuDataTags);
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