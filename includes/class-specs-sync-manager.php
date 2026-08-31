<?php
namespace HWsync;

use HWsync\Models\Component;
use HWsync\Models\Vendor_Price;
use HWsync\Models\Vendor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Specs_Sync_Manager {

	/**
	 * Exact allowed specification attributes per category.
	 * Only these specifications will be synced and stored for each hardware category.
	 */
	public static $allowed_specs_by_category = array(
		'gpu' => array(
			'GPU Name',
			'Architecture',
			'Shading Units',
			'TMUs',
			'ROPs',
			'Compute Units',
			'Matrix Cores',
			'RT Cores',
			'Base Clock',
			'Game Clock',
			'Boost Clock',
			'Memory Clock',
			'Memory Size',
			'Memory Type',
			'Memory Bus',
			'Bandwidth',
			'Slot Width',
			'TDP',
			'Suggested PSU',
			'Outputs',
			'Power Connectors',
		),
		'cpu' => array(
			'Socket',
			'Frequency',
			'Turbo Clock',
			'Number of Cores',
			'Number of Threads',
			'Integrated Graphics',
			'Codename',
			'Generation',
			'Memory Support',
			'Rated Speed',
			'Memory Bus',
			'Memory Bandwidth',
			'TDP',
			'PPT',
			'ECC Memory',
			'PCI-Express',
			'Chipsets',
			'Cache L1',
			'Cache L2',
			'Cache L3',
			'Features',
		),
		'motherboard' => array(
			'Platform',
			'Socket',
			'Cpu Type',
			'Chipset',
			'Memory Speed',
			'Max Memory Support',
			'Supported Memory Type',
			'Channel Supported',
			'Memory Feature',
			'Graphics Port',
			'Expansion Slots',
			'Back Panel I/O Ports',
			'Internal I/O Connector',
			'Form Factor',
			'Warranty',
		),
		'cooler' => array(
			'Cooling Type',
			'Socket Support',
			'Fan Size',
			'PWM Controller',
			'Radiator Size',
			'Lighting',
			'Warranty',
		),
		'ram' => array(
			'Model',
			'Product Series',
			'Memory Type',
			'Capacity',
			'Lighting',
			'Kit Type',
			'Speed',
			'Tested Latency',
			'Tested Voltage',
			'Dimm Type',
			'Profile Type',
			'Warranty',
		),
		'psu' => array(
			'Wattage',
			'Series',
			'Certification',
			'Modular',
			'PCIe Connector (6+2)',
			'SATA Connector',
			'Peripheral (4-Pin)',
			'Warranty',
		),
		'cabinet' => array(
			'Cabinet Size',
			'Color',
			'Material',
			'Expansion Slots',
			'Motherboard Size',
			'Max CPU Cooler Height',
			'Max PSU Length',
			'Max Gpu Length',
			'Max 3.5" HDD',
			'Max 2.5" SSD',
			'Dust Filters',
			'Pre Installed Fans',
			'Max Fan Support',
			'Radiator Support',
			'I/O Panel',
			'Warranty',
		),
		'storage' => array(
			'Category',
			'Series',
			'Capacity',
			'Form Factor',
			'NVMe',
			'Interface',
			'Write Speed',
			'Read Speed',
			'TBW',
			'Warranty',
		),
		'case_fan' => array(
			'Fan Size',
			'Fan Speed',
			'Airflow',
			'Noise Level',
			'Bearing Type',
			'Lighting',
			'PWM Support',
			'Connector',
			'Package Quantity',
			'Color',
			'Warranty',
		),
	);

	/**
	 * Canonical Motherboard Chipset Map (Platform, Socket, Default Supported Memory).
	 */
	public static $motherboard_chipsets = array(
		// AMD AM5 Chipsets
		'X870E' => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),
		'X870'  => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),
		'X670E' => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),
		'X670'  => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),
		'B850'  => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),
		'B840'  => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),
		'B650E' => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),
		'B650'  => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),
		'A620'  => array( 'platform' => 'AMD', 'socket' => 'AM5', 'mem' => 'DDR5' ),

		// AMD AM4 Chipsets
		'X570S' => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),
		'X570'  => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),
		'B550'  => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),
		'A520'  => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),
		'X470'  => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),
		'B450'  => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),
		'X370'  => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),
		'B350'  => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),
		'A320'  => array( 'platform' => 'AMD', 'socket' => 'AM4', 'mem' => 'DDR4' ),

		// AMD Threadripper
		'WRX90' => array( 'platform' => 'AMD', 'socket' => 'sTR5', 'mem' => 'DDR5' ),
		'TRX50' => array( 'platform' => 'AMD', 'socket' => 'sTR5', 'mem' => 'DDR5' ),
		'WRX80' => array( 'platform' => 'AMD', 'socket' => 'sWRX8', 'mem' => 'DDR4' ),
		'TRX40' => array( 'platform' => 'AMD', 'socket' => 'sTRX4', 'mem' => 'DDR4' ),
		'X399'  => array( 'platform' => 'AMD', 'socket' => 'TR4', 'mem' => 'DDR4' ),

		// Intel LGA1851 Chipsets
		'Z890'  => array( 'platform' => 'Intel', 'socket' => 'LGA1851', 'mem' => 'DDR5' ),
		'B860'  => array( 'platform' => 'Intel', 'socket' => 'LGA1851', 'mem' => 'DDR5' ),
		'H810'  => array( 'platform' => 'Intel', 'socket' => 'LGA1851', 'mem' => 'DDR5' ),

		// Intel LGA1700 Chipsets
		'Z790'  => array( 'platform' => 'Intel', 'socket' => 'LGA1700', 'mem' => '' ),
		'B760'  => array( 'platform' => 'Intel', 'socket' => 'LGA1700', 'mem' => '' ),
		'H770'  => array( 'platform' => 'Intel', 'socket' => 'LGA1700', 'mem' => '' ),
		'Z690'  => array( 'platform' => 'Intel', 'socket' => 'LGA1700', 'mem' => '' ),
		'B660'  => array( 'platform' => 'Intel', 'socket' => 'LGA1700', 'mem' => '' ),
		'H670'  => array( 'platform' => 'Intel', 'socket' => 'LGA1700', 'mem' => '' ),
		'H610'  => array( 'platform' => 'Intel', 'socket' => 'LGA1700', 'mem' => '' ),

		// Intel LGA1200 Chipsets
		'Z590'  => array( 'platform' => 'Intel', 'socket' => 'LGA1200', 'mem' => 'DDR4' ),
		'B560'  => array( 'platform' => 'Intel', 'socket' => 'LGA1200', 'mem' => 'DDR4' ),
		'H570'  => array( 'platform' => 'Intel', 'socket' => 'LGA1200', 'mem' => 'DDR4' ),
		'H510'  => array( 'platform' => 'Intel', 'socket' => 'LGA1200', 'mem' => 'DDR4' ),
		'Z490'  => array( 'platform' => 'Intel', 'socket' => 'LGA1200', 'mem' => 'DDR4' ),
		'B460'  => array( 'platform' => 'Intel', 'socket' => 'LGA1200', 'mem' => 'DDR4' ),
		'H470'  => array( 'platform' => 'Intel', 'socket' => 'LGA1200', 'mem' => 'DDR4' ),
		'H410'  => array( 'platform' => 'Intel', 'socket' => 'LGA1200', 'mem' => 'DDR4' ),

		// Intel LGA1151 Chipsets
		'Z390'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'Z370'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'B365'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'B360'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'H370'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'H310'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'Z270'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'H270'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'B250'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'Z170'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'H170'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'B150'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),
		'H110'  => array( 'platform' => 'Intel', 'socket' => 'LGA1151', 'mem' => 'DDR4' ),

		// Intel LGA2066 / LGA2011-3 / LGA1150
		'X299'  => array( 'platform' => 'Intel', 'socket' => 'LGA2066', 'mem' => 'DDR4' ),
		'X99'   => array( 'platform' => 'Intel', 'socket' => 'LGA2011-v3', 'mem' => 'DDR4' ),
		'Z97'   => array( 'platform' => 'Intel', 'socket' => 'LGA1150', 'mem' => 'DDR3' ),
		'H97'   => array( 'platform' => 'Intel', 'socket' => 'LGA1150', 'mem' => 'DDR3' ),
		'Z87'   => array( 'platform' => 'Intel', 'socket' => 'LGA1150', 'mem' => 'DDR3' ),
		'H87'   => array( 'platform' => 'Intel', 'socket' => 'LGA1150', 'mem' => 'DDR3' ),
		'B85'   => array( 'platform' => 'Intel', 'socket' => 'LGA1150', 'mem' => 'DDR3' ),
		'H81'   => array( 'platform' => 'Intel', 'socket' => 'LGA1150', 'mem' => 'DDR3' ),
	);

	/**
	 * Core/essential attributes per category for completeness evaluation.
	 */
	public static $core_specs_by_category = array(
		'cpu'         => array( 'Socket', 'Number of Cores', 'Number of Threads', 'Frequency', 'Turbo Clock', 'Cache L3', 'TDP', 'Memory Support' ),
		'gpu'         => array( 'GPU Name', 'Memory Size', 'Memory Type', 'Memory Bus', 'Suggested PSU', 'TDP' ),
		'motherboard' => array( 'Platform', 'Socket', 'Chipset', 'Form Factor', 'Supported Memory Type' ),
		'cooler'      => array( 'Cooling Type', 'Socket Support', 'Fan Size' ),
		'ram'         => array( 'Memory Type', 'Capacity', 'Speed', 'Tested Latency' ),
		'psu'         => array( 'Wattage', 'Certification', 'Modular' ),
		'cabinet'     => array( 'Cabinet Size', 'Motherboard Size', 'Max Gpu Length' ),
		'storage'     => array( 'Capacity', 'Form Factor', 'Interface', 'Read Speed' ),
		'case_fan'    => array( 'Fan Size', 'Fan Speed', 'Airflow', 'Lighting' ),
	);

	/**
	 * Synonym dictionaries mapping vendor variations into canonical schema keys.
	 */
	public static $category_synonyms_map = array(
		'cpu' => array(
			'cpu socket'                            => 'Socket',
			'cpu socket type'                       => 'Socket',
			'socket type'                           => 'Socket',
			'sockets supported'                     => 'Socket',
			'socket'                                => 'Socket',

			'processor base frequency'              => 'Frequency',
			'base clock'                            => 'Frequency',
			'base frequency'                        => 'Frequency',
			'cpu base clock'                        => 'Frequency',
			'clock speed'                           => 'Frequency',
			'frequency'                             => 'Frequency',

			'max turbo frequency'                   => 'Turbo Clock',
			'boost clock'                           => 'Turbo Clock',
			'turbo frequency'                       => 'Turbo Clock',
			'max boost clock'                       => 'Turbo Clock',
			'max boost clock speed'                 => 'Turbo Clock',
			'processor max frequency'               => 'Turbo Clock',
			'turbo clock'                           => 'Turbo Clock',
			'intel turbo boost max technology 3.0 frequency' => 'Turbo Clock',

			'total cores'                           => 'Number of Cores',
			'cpu cores'                             => 'Number of Cores',
			'processor cores'                       => 'Number of Cores',
			'core count'                            => 'Number of Cores',
			'cores'                                 => 'Number of Cores',
			'no of cores'                           => 'Number of Cores',
			'# of cores'                            => 'Number of Cores',
			'number of cores'                       => 'Number of Cores',
			'# of performance-cores'                => 'Number of Cores',

			'total threads'                         => 'Number of Threads',
			'threads'                               => 'Number of Threads',
			'thread count'                          => 'Number of Threads',
			'# of threads'                          => 'Number of Threads',
			'no of threads'                         => 'Number of Threads',
			'number of threads'                     => 'Number of Threads',

			'processor graphics'                    => 'Integrated Graphics',
			'graphics'                              => 'Integrated Graphics',
			'integrated graphics'                   => 'Integrated Graphics',
			'integrated gpu'                        => 'Integrated Graphics',
			'on-chip graphics'                      => 'Integrated Graphics',
			'gpu'                                   => 'Integrated Graphics',

			'code name'                             => 'Codename',
			'codename'                              => 'Codename',
			'architecture codename'                 => 'Codename',
			'products formerly'                     => 'Codename',

			'product collection'                    => 'Generation',
			'cpu generation'                        => 'Generation',
			'processor generation'                  => 'Generation',
			'generation'                            => 'Generation',
			'series'                                => 'Generation',

			'memory types'                          => 'Memory Support',
			'supported memory'                      => 'Memory Support',
			'memory type'                           => 'Memory Support',
			'dram support'                          => 'Memory Support',
			'memory support'                        => 'Memory Support',

			'max memory speed'                      => 'Rated Speed',
			'supported memory speed'                => 'Rated Speed',
			'rated speed'                           => 'Rated Speed',

			'memory channels'                       => 'Memory Bus',
			'max # of memory channels'              => 'Memory Bus',
			'max memory channels'                   => 'Memory Bus',
			'memory bus'                            => 'Memory Bus',

			'max memory bandwidth'                  => 'Memory Bandwidth',
			'memory bandwidth'                      => 'Memory Bandwidth',

			'processor base power'                  => 'TDP',
			'tdp (base power)'                      => 'TDP',
			'thermal design power'                  => 'TDP',
			'base power'                            => 'TDP',
			'tdp'                                   => 'TDP',

			'maximum turbo power'                   => 'PPT',
			'package power tracking'                => 'PPT',
			'max turbo power'                       => 'PPT',
			'turbo power'                           => 'PPT',
			'ppt'                                   => 'PPT',

			'ecc memory supported'                  => 'ECC Memory',
			'ecc support'                           => 'ECC Memory',
			'ecc memory'                            => 'ECC Memory',

			'pci express revision'                  => 'PCI-Express',
			'pcie version'                          => 'PCI-Express',
			'pci express configurations'            => 'PCI-Express',
			'max # of pci express lanes'            => 'PCI-Express',
			'pcie lanes'                            => 'PCI-Express',
			'pci-express'                           => 'PCI-Express',

			'chipset support'                       => 'Chipsets',
			'supported chipsets'                    => 'Chipsets',
			'compatible chipsets'                   => 'Chipsets',
			'chipsets'                              => 'Chipsets',

			'l1 cache'                              => 'Cache L1',
			'total l1 cache'                        => 'Cache L1',
			'cache l1'                              => 'Cache L1',

			'l2 cache'                              => 'Cache L2',
			'total l2 cache'                        => 'Cache L2',
			'cache l2'                              => 'Cache L2',

			'l3 cache'                              => 'Cache L3',
			'cache'                                 => 'Cache L3',
			'intel smart cache'                     => 'Cache L3',
			'amd 3d v-cache'                        => 'Cache L3',
			'total l3 cache'                        => 'Cache L3',
			'cache l3'                              => 'Cache L3',

			'technologies supported'                => 'Features',
			'advanced technologies'                 => 'Features',
			'instruction set'                       => 'Features',
			'features'                              => 'Features',
		),

		'gpu' => array(
			'gpu chipset'                           => 'GPU Name',
			'graphics processor'                    => 'GPU Name',
			'gpu engine'                            => 'GPU Name',
			'chipset'                               => 'GPU Name',
			'gpu model'                             => 'GPU Name',
			'gpu name'                              => 'GPU Name',
			'gpu'                                   => 'GPU Name',

			'gpu architecture'                      => 'Architecture',
			'microarchitecture'                     => 'Architecture',
			'architecture'                          => 'Architecture',
			'arch'                                  => 'Architecture',

			'cuda cores'                            => 'Shading Units',
			'stream processors'                     => 'Shading Units',
			'shaders'                               => 'Shading Units',
			'shader cores'                          => 'Shading Units',
			'sp'                                    => 'Shading Units',
			'shading units'                         => 'Shading Units',
			'cuda core count'                       => 'Shading Units',

			'texture mapping units'                 => 'TMUs',
			'tmu'                                   => 'TMUs',
			'tmus'                                  => 'TMUs',

			'render output units'                   => 'ROPs',
			'render output processors'              => 'ROPs',
			'rop'                                   => 'ROPs',
			'rops'                                  => 'ROPs',

			'compute units'                         => 'Compute Units',
			'cu'                                    => 'Compute Units',
			'sm'                                    => 'Compute Units',
			'streaming multiprocessors'             => 'Compute Units',

			'tensor cores'                          => 'Matrix Cores',
			'matrix cores'                          => 'Matrix Cores',
			'matrix processors'                     => 'Matrix Cores',
			'ai cores'                              => 'Matrix Cores',
			'xmx'                                   => 'Matrix Cores',

			'ray tracing cores'                     => 'RT Cores',
			'rt cores'                              => 'RT Cores',
			'ray accelerators'                      => 'RT Cores',

			'core clock'                            => 'Base Clock',
			'engine clock'                          => 'Base Clock',
			'base frequency'                        => 'Base Clock',
			'gpu base clock'                        => 'Base Clock',
			'base clock'                            => 'Base Clock',

			'gaming clock'                          => 'Game Clock',
			'game frequency'                        => 'Game Clock',
			'game clock'                            => 'Game Clock',

			'max clock'                             => 'Boost Clock',
			'boost frequency'                       => 'Boost Clock',
			'gpu boost clock'                       => 'Boost Clock',
			'turbo clock'                           => 'Boost Clock',
			'boost clock'                           => 'Boost Clock',

			'memory speed'                          => 'Memory Clock',
			'memory frequency'                      => 'Memory Clock',
			'effective memory clock'                => 'Memory Clock',
			'memory clock'                          => 'Memory Clock',

			'vram'                                  => 'Memory Size',
			'vram size'                             => 'Memory Size',
			'memory capacity'                       => 'Memory Size',
			'graphics memory size'                  => 'Memory Size',
			'video memory'                          => 'Memory Size',
			'memory size'                           => 'Memory Size',

			'vram type'                             => 'Memory Type',
			'memory interface type'                 => 'Memory Type',
			'gddr type'                             => 'Memory Type',
			'graphics memory type'                  => 'Memory Type',
			'memory type'                           => 'Memory Type',

			'memory interface'                      => 'Memory Bus',
			'bus width'                             => 'Memory Bus',
			'memory bus width'                      => 'Memory Bus',
			'bus interface'                         => 'Memory Bus',
			'memory bus'                            => 'Memory Bus',

			'memory bandwidth'                      => 'Bandwidth',
			'max memory bandwidth'                  => 'Bandwidth',
			'bandwidth'                             => 'Bandwidth',

			'slot size'                             => 'Slot Width',
			'slots'                                 => 'Slot Width',
			'card dimension (slots)'                => 'Slot Width',
			'slot width'                            => 'Slot Width',
			'dimensions (slots)'                    => 'Slot Width',

			'power consumption'                     => 'TDP',
			'board power'                           => 'TDP',
			'total graphics power'                  => 'TDP',
			'tgp'                                   => 'TDP',
			'power draw'                            => 'TDP',
			'tdp'                                   => 'TDP',

			'recommended psu'                       => 'Suggested PSU',
			'minimum psu'                           => 'Suggested PSU',
			'power requirement'                     => 'Suggested PSU',
			'recommended power supply'              => 'Suggested PSU',
			'min system power'                      => 'Suggested PSU',
			'suggested psu'                         => 'Suggested PSU',

			'display outputs'                       => 'Outputs',
			'display ports'                         => 'Outputs',
			'i/o ports'                             => 'Outputs',
			'video output ports'                    => 'Outputs',
			'connectors'                            => 'Outputs',
			'ports'                                 => 'Outputs',
			'outputs'                               => 'Outputs',

			'power connector'                       => 'Power Connectors',
			'supplementary power connectors'        => 'Power Connectors',
			'power input'                           => 'Power Connectors',
			'power connectors'                      => 'Power Connectors',
		),

		'motherboard' => array(
			'cpu platform'                          => 'Platform',
			'platform type'                         => 'Platform',
			'supported platform'                    => 'Platform',
			'cpu family'                            => 'Platform',
			'platform'                              => 'Platform',

			'cpu socket'                            => 'Socket',
			'socket type'                           => 'Socket',
			'cpu socket type'                       => 'Socket',
			'supported socket'                      => 'Socket',
			'socket'                                => 'Socket',

			'supported cpu'                         => 'Cpu Type',
			'cpu support'                           => 'Cpu Type',
			'processor support'                     => 'Cpu Type',
			'supported processors'                  => 'Cpu Type',
			'cpu type'                              => 'Cpu Type',

			'motherboard chipset'                   => 'Chipset',
			'chipset model'                         => 'Chipset',
			'system chipset'                        => 'Chipset',
			'chipset'                               => 'Chipset',

			'supported memory speed'                => 'Memory Speed',
			'memory frequency'                      => 'Memory Speed',
			'memory clock'                          => 'Memory Speed',
			'memory oc speed'                       => 'Memory Speed',
			'memory speed'                          => 'Memory Speed',

			'max memory capacity'                   => 'Max Memory Support',
			'max memory'                            => 'Max Memory Support',
			'maximum memory'                        => 'Max Memory Support',
			'max ram'                               => 'Max Memory Support',
			'max memory size'                       => 'Max Memory Support',
			'max memory support'                    => 'Max Memory Support',

			'memory type'                           => 'Supported Memory Type',
			'memory types'                          => 'Supported Memory Type',
			'dram standard'                         => 'Supported Memory Type',
			'ddr support'                           => 'Supported Memory Type',
			'supported memory type'                 => 'Supported Memory Type',

			'memory channel'                        => 'Channel Supported',
			'channel architecture'                  => 'Channel Supported',
			'memory channels'                       => 'Channel Supported',
			'channel supported'                     => 'Channel Supported',

			'memory slots'                          => 'Memory Feature',
			'ecc support'                           => 'Memory Feature',
			'xmp support'                           => 'Memory Feature',
			'expo support'                          => 'Memory Feature',
			'dimm slots'                            => 'Memory Feature',
			'memory feature'                        => 'Memory Feature',
			'memory features'                       => 'Memory Feature',

			'video outputs'                         => 'Graphics Port',
			'onboard graphics output'               => 'Graphics Port',
			'display outputs'                       => 'Graphics Port',
			'hdmi / displayport'                    => 'Graphics Port',
			'graphics port'                         => 'Graphics Port',

			'pcie slots'                            => 'Expansion Slots',
			'expansion slot'                        => 'Expansion Slots',
			'pci express slots'                     => 'Expansion Slots',
			'pcie x16'                              => 'Expansion Slots',
			'expansion slots'                       => 'Expansion Slots',

			'rear i/o ports'                        => 'Back Panel I/O Ports',
			'back panel ports'                      => 'Back Panel I/O Ports',
			'rear panel ports'                      => 'Back Panel I/O Ports',
			'rear i/o'                              => 'Back Panel I/O Ports',
			'back panel i/o ports'                  => 'Back Panel I/O Ports',

			'internal connectors'                   => 'Internal I/O Connector',
			'internal headers'                      => 'Internal I/O Connector',
			'front panel headers'                   => 'Internal I/O Connector',
			'm.2 slots'                             => 'Internal I/O Connector',
			'sata ports'                            => 'Internal I/O Connector',
			'internal i/o connector'                => 'Internal I/O Connector',

			'board form factor'                     => 'Form Factor',
			'motherboard form factor'               => 'Form Factor',
			'dimension form factor'                 => 'Form Factor',
			'form factor'                           => 'Form Factor',

			'warranty period'                       => 'Warranty',
			'manufacturer warranty'                 => 'Warranty',
			'warranty terms'                        => 'Warranty',
			'warranty'                              => 'Warranty',
		),

		'cooler' => array(
			'cooler type'                           => 'Cooling Type',
			'type'                                  => 'Cooling Type',
			'air cooler / aio'                      => 'Cooling Type',
			'radiator type'                         => 'Cooling Type',
			'cooling type'                          => 'Cooling Type',

			'supported sockets'                     => 'Socket Support',
			'cpu socket support'                    => 'Socket Support',
			'compatibility'                         => 'Socket Support',
			'socket compatibility'                  => 'Socket Support',
			'cpu socket'                            => 'Socket Support',
			'socket support'                        => 'Socket Support',

			'fan dimensions'                        => 'Fan Size',
			'fan diameter'                          => 'Fan Size',
			'fans size'                             => 'Fan Size',
			'fan size'                              => 'Fan Size',

			'pwm support'                           => 'PWM Controller',
			'pwm mode'                              => 'PWM Controller',
			'fan control'                           => 'PWM Controller',
			'pwm controller'                        => 'PWM Controller',
			'pwm'                                   => 'PWM Controller',

			'radiator dimensions'                   => 'Radiator Size',
			'radiator length'                       => 'Radiator Size',
			'radiator thickness'                    => 'Radiator Size',
			'size'                                  => 'Radiator Size',
			'radiator size'                         => 'Radiator Size',

			'led type'                              => 'Lighting',
			'rgb lighting'                          => 'Lighting',
			'argb'                                  => 'Lighting',
			'lighting type'                         => 'Lighting',
			'led'                                   => 'Lighting',
			'lighting'                              => 'Lighting',

			'warranty period'                       => 'Warranty',
			'manufacturer warranty'                 => 'Warranty',
			'warranty'                              => 'Warranty',
		),

		'ram' => array(
			'model number'                          => 'Model',
			'part number'                           => 'Model',
			'mpn'                                   => 'Model',
			'sku'                                   => 'Model',
			'model name'                            => 'Model',
			'model'                                 => 'Model',

			'series'                                => 'Product Series',
			'family'                                => 'Product Series',
			'line'                                  => 'Product Series',
			'product line'                          => 'Product Series',
			'product series'                        => 'Product Series',

			'ddr type'                              => 'Memory Type',
			'dram type'                             => 'Memory Type',
			'memory standard'                       => 'Memory Type',
			'memory type'                           => 'Memory Type',

			'memory capacity'                       => 'Capacity',
			'size'                                  => 'Capacity',
			'total capacity'                        => 'Capacity',
			'kit capacity'                          => 'Capacity',
			'capacity'                              => 'Capacity',

			'rgb'                                   => 'Lighting',
			'argb'                                  => 'Lighting',
			'rgb lighting'                          => 'Lighting',
			'led'                                   => 'Lighting',
			'led lighting'                          => 'Lighting',
			'lighting'                              => 'Lighting',

			'single / dual kit'                     => 'Kit Type',
			'channel configuration'                 => 'Kit Type',
			'kit configuration'                     => 'Kit Type',
			'module type'                           => 'Kit Type',
			'kit type'                              => 'Kit Type',

			'memory speed'                          => 'Speed',
			'tested speed'                          => 'Speed',
			'frequency'                             => 'Speed',
			'clock speed'                           => 'Speed',
			'rated speed'                           => 'Speed',
			'speed'                                 => 'Speed',

			'latency'                               => 'Tested Latency',
			'cas latency'                           => 'Tested Latency',
			'timings'                               => 'Tested Latency',
			'tested timings'                        => 'Tested Latency',
			'cl'                                    => 'Tested Latency',
			'tested latency'                        => 'Tested Latency',

			'voltage'                               => 'Tested Voltage',
			'operating voltage'                     => 'Tested Voltage',
			'tested operating voltage'              => 'Tested Voltage',
			'tested voltage'                        => 'Tested Voltage',

			'module dimension'                      => 'Dimm Type',
			'pin count'                             => 'Dimm Type',
			'dimm'                                  => 'Dimm Type',
			'dimm type'                             => 'Dimm Type',

			'xmp / expo'                            => 'Profile Type',
			'intel xmp'                             => 'Profile Type',
			'amd expo'                              => 'Profile Type',
			'performance profile'                   => 'Profile Type',
			'memory profile'                        => 'Profile Type',
			'profile type'                          => 'Profile Type',

			'warranty period'                       => 'Warranty',
			'manufacturer warranty'                 => 'Warranty',
			'limited lifetime warranty'             => 'Warranty',
			'warranty'                              => 'Warranty',
		),

		'psu' => array(
			'total power'                           => 'Wattage',
			'max power'                             => 'Wattage',
			'power output'                          => 'Wattage',
			'continuous power'                      => 'Wattage',
			'power'                                 => 'Wattage',
			'watt'                                  => 'Wattage',
			'wattage'                               => 'Wattage',

			'product series'                        => 'Series',
			'model series'                          => 'Series',
			'family'                                => 'Series',
			'series'                                => 'Series',

			'80 plus rating'                        => 'Certification',
			'80 plus certification'                 => 'Certification',
			'efficiency certification'              => 'Certification',
			'efficiency rating'                     => 'Certification',
			'80+'                                   => 'Certification',
			'certification'                         => 'Certification',

			'modularity'                            => 'Modular',
			'cable type'                            => 'Modular',
			'modular type'                          => 'Modular',
			'full / semi modular'                   => 'Modular',
			'modular'                               => 'Modular',

			'pcie connectors'                       => 'PCIe Connector (6+2)',
			'pcie 8 pin'                            => 'PCIe Connector (6+2)',
			'pcie 6+2 pin'                          => 'PCIe Connector (6+2)',
			'pcie 5.0 12vhpwr'                      => 'PCIe Connector (6+2)',
			'pcie connector (6+2)'                  => 'PCIe Connector (6+2)',
			'pcie'                                  => 'PCIe Connector (6+2)',

			'sata power connectors'                 => 'SATA Connector',
			'sata ports'                            => 'SATA Connector',
			'sata connectors'                       => 'SATA Connector',
			'sata'                                  => 'SATA Connector',
			'sata connector'                        => 'SATA Connector',

			'molex connectors'                      => 'Peripheral (4-Pin)',
			'molex 4-pin'                           => 'Peripheral (4-Pin)',
			'peripheral connectors'                 => 'Peripheral (4-Pin)',
			'floppy connectors'                     => 'Peripheral (4-Pin)',
			'molex'                                 => 'Peripheral (4-Pin)',
			'peripheral (4-pin)'                    => 'Peripheral (4-Pin)',

			'warranty period'                       => 'Warranty',
			'manufacturer warranty'                 => 'Warranty',
			'warranty'                              => 'Warranty',
		),

		'cabinet' => array(
			'case type'                             => 'Cabinet Size',
			'form factor'                           => 'Cabinet Size',
			'chassis type'                          => 'Cabinet Size',
			'case size'                             => 'Cabinet Size',
			'tower type'                            => 'Cabinet Size',
			'mid tower'                             => 'Cabinet Size',
			'full tower'                            => 'Cabinet Size',
			'cabinet size'                          => 'Cabinet Size',

			'case color'                            => 'Color',
			'chassis color'                         => 'Color',
			'exterior color'                        => 'Color',
			'colors'                                => 'Color',
			'color'                                 => 'Color',

			'case material'                         => 'Material',
			'chassis material'                      => 'Material',
			'side panel material'                   => 'Material',
			'materials'                             => 'Material',
			'tempered glass'                        => 'Material',
			'material'                              => 'Material',

			'pci slots'                             => 'Expansion Slots',
			'expansion slot count'                  => 'Expansion Slots',
			'slots'                                 => 'Expansion Slots',
			'expansion slots'                       => 'Expansion Slots',

			'supported motherboards'                => 'Motherboard Size',
			'motherboard support'                   => 'Motherboard Size',
			'mb support'                            => 'Motherboard Size',
			'form factor support'                   => 'Motherboard Size',
			'motherboard size'                      => 'Motherboard Size',

			'cpu cooler clearance'                  => 'Max CPU Cooler Height',
			'maximum cpu cooler height'             => 'Max CPU Cooler Height',
			'cpu cooler limit'                      => 'Max CPU Cooler Height',
			'max cpu cooler height'                 => 'Max CPU Cooler Height',

			'psu clearance'                         => 'Max PSU Length',
			'maximum psu length'                    => 'Max PSU Length',
			'psu limit'                             => 'Max PSU Length',
			'max psu length'                        => 'Max PSU Length',

			'gpu clearance'                         => 'Max Gpu Length',
			'maximum gpu length'                    => 'Max Gpu Length',
			'vga length'                            => 'Max Gpu Length',
			'max vga length'                        => 'Max Gpu Length',
			'graphic card clearance'                => 'Max Gpu Length',
			'max gpu length'                        => 'Max Gpu Length',

			'3.5" drive bays'                       => 'Max 3.5" HDD',
			'3.5 inch bays'                         => 'Max 3.5" HDD',
			'hdd bays'                              => 'Max 3.5" HDD',
			'3.5 hdd'                               => 'Max 3.5" HDD',
			'max 3.5" hdd'                          => 'Max 3.5" HDD',

			'2.5" drive bays'                       => 'Max 2.5" SSD',
			'2.5 inch bays'                         => 'Max 2.5" SSD',
			'ssd bays'                              => 'Max 2.5" SSD',
			'2.5 ssd'                               => 'Max 2.5" SSD',
			'max 2.5" ssd'                          => 'Max 2.5" SSD',

			'filter'                                => 'Dust Filters',
			'filters'                               => 'Dust Filters',
			'dust filter location'                  => 'Dust Filters',
			'removable dust filter'                 => 'Dust Filters',
			'dust filters'                          => 'Dust Filters',

			'included fans'                         => 'Pre Installed Fans',
			'fan included'                          => 'Pre Installed Fans',
			'installed fans'                        => 'Pre Installed Fans',
			'front/rear fans included'              => 'Pre Installed Fans',
			'pre installed fans'                    => 'Pre Installed Fans',

			'fan support'                           => 'Max Fan Support',
			'fan mounting locations'                => 'Max Fan Support',
			'total fan support'                     => 'Max Fan Support',
			'max fan support'                       => 'Max Fan Support',

			'supported radiators'                   => 'Radiator Support',
			'radiator mounting'                     => 'Radiator Support',
			'water cooling support'                 => 'Radiator Support',
			'radiator support'                      => 'Radiator Support',

			'front i/o ports'                       => 'I/O Panel',
			'front panel ports'                     => 'I/O Panel',
			'top i/o ports'                         => 'I/O Panel',
			'i/o ports'                             => 'I/O Panel',
			'front panel connectors'                => 'I/O Panel',
			'i/o panel'                             => 'I/O Panel',

			'warranty period'                       => 'Warranty',
			'manufacturer warranty'                 => 'Warranty',
			'warranty'                              => 'Warranty',
		),

		'storage' => array(
			'drive type'                            => 'Category',
			'storage type'                          => 'Category',
			'ssd type'                              => 'Category',
			'product type'                          => 'Category',
			'category'                              => 'Category',

			'product series'                        => 'Series',
			'model series'                          => 'Series',
			'family'                                => 'Series',
			'line'                                  => 'Series',
			'series'                                => 'Series',

			'storage capacity'                      => 'Capacity',
			'size'                                  => 'Capacity',
			'total capacity'                        => 'Capacity',
			'usable capacity'                       => 'Capacity',
			'capacity'                              => 'Capacity',

			'drive form factor'                     => 'Form Factor',
			'module size'                           => 'Form Factor',
			'm.2 2280'                              => 'Form Factor',
			'2.5 inch'                              => 'Form Factor',
			'form factor'                           => 'Form Factor',

			'nvme version'                          => 'NVMe',
			'protocol'                              => 'NVMe',
			'nvme support'                          => 'NVMe',
			'nvme express'                          => 'NVMe',
			'nvme'                                  => 'NVMe',

			'bus interface'                         => 'Interface',
			'host interface'                        => 'Interface',
			'pcie gen'                              => 'Interface',
			'sata 6gb/s'                            => 'Interface',
			'pcie 4.0 x4'                           => 'Interface',
			'pcie 5.0 x4'                           => 'Interface',
			'interface'                             => 'Interface',

			'sequential write'                      => 'Write Speed',
			'max write speed'                       => 'Write Speed',
			'sequential write speed'                => 'Write Speed',
			'write speed'                           => 'Write Speed',

			'sequential read'                       => 'Read Speed',
			'max read speed'                        => 'Read Speed',
			'sequential read speed'                 => 'Read Speed',
			'read speed'                            => 'Read Speed',

			'terabytes written'                     => 'TBW',
			'endurance'                             => 'TBW',
			'endurance (tbw)'                       => 'TBW',
			'lifetime write'                        => 'TBW',
			'tbw'                                   => 'TBW',

			'warranty period'                       => 'Warranty',
			'manufacturer warranty'                 => 'Warranty',
			'limited warranty'                      => 'Warranty',
			'warranty'                              => 'Warranty',
		),
		'case_fan' => array(
			'fan size'                              => 'Fan Size',
			'dimensions'                            => 'Fan Size',
			'size'                                  => 'Fan Size',
			'fan dimensions'                        => 'Fan Size',

			'fan speed'                             => 'Fan Speed',
			'speed'                                 => 'Fan Speed',
			'rpm'                                   => 'Fan Speed',
			'fan speed (rpm)'                       => 'Fan Speed',

			'airflow'                               => 'Airflow',
			'air flow'                              => 'Airflow',
			'max airflow'                           => 'Airflow',
			'fan airflow'                           => 'Airflow',

			'noise'                                 => 'Noise Level',
			'noise level'                           => 'Noise Level',
			'fan noise level'                       => 'Noise Level',
			'dBA'                                   => 'Noise Level',

			'bearing'                               => 'Bearing Type',
			'bearing type'                          => 'Bearing Type',
			'fan bearing'                           => 'Bearing Type',

			'led'                                   => 'Lighting',
			'rgb'                                   => 'Lighting',
			'argb'                                  => 'Lighting',
			'lighting'                              => 'Lighting',
			'led color'                             => 'Lighting',

			'pwm'                                   => 'PWM Support',
			'pwm control'                           => 'PWM Support',
			'pwm support'                           => 'PWM Support',

			'connector'                             => 'Connector',
			'pin'                                   => 'Connector',
			'fan connector'                         => 'Connector',

			'package quantity'                      => 'Package Quantity',
			'pack'                                  => 'Package Quantity',
			'quantity'                              => 'Package Quantity',
			'number of fans'                        => 'Package Quantity',

			'color'                                 => 'Color',
			'colour'                                => 'Color',

			'warranty period'                       => 'Warranty',
			'manufacturer warranty'                 => 'Warranty',
			'limited warranty'                      => 'Warranty',
			'warranty'                              => 'Warranty',
		),
	);

	/**
	 * Canonical category slug resolver.
	 */
	public static function normalize_category_slug( $category ) {
		$c = strtolower( trim( (string) $category ) );
		if ( in_array( $c, array( 'smps', 'power_supply', 'power-supply' ), true ) ) {
			return 'psu';
		}
		if ( in_array( $c, array( 'case', 'chassis', 'cabinets' ), true ) ) {
			return 'cabinet';
		}
		if ( in_array( $c, array( 'ssd', 'hdd', 'hard_drive', 'hard-drive', 'nvme' ), true ) ) {
			return 'storage';
		}
		if ( in_array( $c, array( 'motherboards', 'mobo', 'mother_board' ), true ) ) {
			return 'motherboard';
		}
		if ( in_array( $c, array( 'coolers', 'aio', 'cpu_cooler', 'liquid_cooler' ), true ) ) {
			return 'cooler';
		}
		if ( in_array( $c, array( 'case_fan', 'case-fan', 'case_fans', 'fan', 'fans', 'cabinet_fan', 'cabinet_fans', 'cabinet-fan' ), true ) ) {
			return 'case_fan';
		}
		return $c;
	}

	/**
	 * Get missing specification keys for a component according to its category schema.
	 *
	 * @param array $specs
	 * @param string $category
	 * @return array List of missing attribute names
	 */
	public static function get_missing_specs( $specs, $category ) {
		$cat = self::normalize_category_slug( $category );
		$allowed = isset( self::$allowed_specs_by_category[ $cat ] ) ? self::$allowed_specs_by_category[ $cat ] : array();
		if ( empty( $allowed ) ) {
			return array();
		}

		$missing = array();
		foreach ( $allowed as $key ) {
			if ( ! isset( $specs[ $key ] ) || trim( (string) $specs[ $key ] ) === '' ) {
				$missing[] = $key;
			}
		}
		return $missing;
	}

	/**
	 * Check if a component's specifications are complete according to its category schema.
	 *
	 * @param array $specs
	 * @param string $category
	 * @return bool
	 */
	public static function is_specs_complete( $specs, $category ) {
		if ( empty( $specs ) || ! is_array( $specs ) ) {
			return false;
		}

		$missing = self::get_missing_specs( $specs, $category );
		return empty( $missing );
	}

	/**
	 * Validate whether a key-value pair is a genuine hardware specification,
	 * rejecting footer disclaimers, shipping text, wishlist, notes, and paragraphs.
	 *
	 * @param string $key
	 * @param string $val
	 * @return bool
	 */
	public static function is_valid_spec_pair( $key, $val ) {
		if ( empty( $key ) || empty( $val ) || ! is_scalar( $key ) || ! is_scalar( $val ) ) {
			return false;
		}

		$k = trim( (string) $key );
		$v = trim( (string) $val );

		$k = rtrim( $k, ':' );
		$k = trim( $k, '*' );

		if ( strlen( $k ) < 2 || strlen( $k ) > 80 ) {
			return false;
		}
		if ( strlen( $v ) < 1 || strlen( $v ) > 220 ) {
			return false;
		}

		$k_lower = strtolower( $k );
		$v_lower = strtolower( $v );

		// Blacklisted keys (disclaimers, shipping, UI buttons, store policies, bot verification dictionaries)
		$blacklisted_keys = array(
			'note', 'notes', 'notice', 'disclaimer', 'terms', 'condition', 'conditions',
			'shipping', 'delivery', 'courier', 'dispatch', 'estimated',
			'wishlist', 'compare', 'review', 'reviews', 'rating', 'ratings', 'cart', 'buy now', 'add to',
			'description', 'overview', 'features', 'key features', 'highlights', 'quick overview',
			'tags', 'tax', 'gst', 'emi', 'cod', 'payment', 'in stock', 'out of stock',
			'return', 'policy', 'cancellation', 'refund', 'replacement',
			'standard shipping', 'fast delivery',
			// Cloudflare / Bot verification dictionary keys
			'title', 'content-title', 'content_title', 'challenge', 'turnstile', 'cf_chl', 'cloudflare', 'captcha',
			'cs', 'da', 'de', 'el', 'es', 'fi', 'fr', 'he', 'hi', 'hr', 'hu', 'id', 'it', 'ja', 'ko', 'lt', 'lv', 'nb', 'nl', 'pl', 'pt', 'ro', 'ru', 'sk', 'sl', 'sv', 'th', 'tr', 'uk', 'vi', 'zh',
		);

		foreach ( $blacklisted_keys as $b ) {
			if ( $k_lower === $b || ( strlen( $b ) > 3 && strpos( $k_lower, $b ) !== false ) ) {
				return false;
			}
		}

		// Blacklisted symbol-only or boilerplate values
		$symbol_values = array( '(', ')', '{', '}', '[', ']', '-', '*', '**', '***', ':', ';', 'n/a', 'na', 'null', 'none', 'undefined', 'no' );
		if ( in_array( $v_lower, $symbol_values, true ) ) {
			return false;
		}

		// Blacklisted phrases inside values (including Cloudflare challenge messages)
		$blacklisted_phrases = array(
			'subject to change without notice',
			'delivery typically takes',
			'business days',
			'prices, specifications',
			'all rights reserved',
			'add to cart',
			'add to wishlist',
			'vaše připojení',
			'připojení se ověřuje',
			'just a moment',
			'checking your browser',
			'turnstile',
		);

		foreach ( $blacklisted_phrases as $phrase ) {
			if ( strpos( $v_lower, $phrase ) !== false ) {
				return false;
			}
		}

		// Reject long descriptive paragraphs
		if ( substr_count( $v, '.' ) > 3 && strlen( $v ) > 120 ) {
			return false;
		}

		// Reject identical key-value (except valid hardware spec acronyms)
		$allowed_identical = array( 'pwm', 'argb', 'rgb', 'nvme', 'ddr4', 'ddr5', 'ddr3', 'pcie' );
		if ( strcasecmp( $k, $v ) === 0 && ! in_array( $k_lower, $allowed_identical, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize a raw specification label into canonical category schema key.
	 *
	 * @param string $key
	 * @param string $category
	 * @return string|null Canonical key name or Title Case string.
	 */
	public static function normalize_spec_key( $key, $category = '' ) {
		$k = trim( (string) $key );
		$k = rtrim( $k, ':' );
		$k = trim( $k, '*' );
		$k_clean = strtolower( preg_replace( '/[^a-zA-Z0-9#"+\/.\s-]/', '', $k ) );
		$k_clean = preg_replace( '/\s+/', ' ', $k_clean );

		$cat = self::normalize_category_slug( $category );

		// Category-specific synonym dictionary match
		if ( ! empty( $cat ) && isset( self::$category_synonyms_map[ $cat ] ) ) {
			if ( isset( self::$category_synonyms_map[ $cat ][ $k_clean ] ) ) {
				return self::$category_synonyms_map[ $cat ][ $k_clean ];
			}

			// Substring / fuzzy synonym matching
			foreach ( self::$category_synonyms_map[ $cat ] as $syn => $target ) {
				if ( $k_clean === $syn || ( strlen( $syn ) >= 4 && strpos( $k_clean, $syn ) !== false ) ) {
					return $target;
				}
			}

			// Direct match against category allowed list
			if ( isset( self::$allowed_specs_by_category[ $cat ] ) ) {
				foreach ( self::$allowed_specs_by_category[ $cat ] as $allowed_k ) {
					if ( strtolower( $allowed_k ) === $k_clean ) {
						return $allowed_k;
					}
				}
			}
		}

		// Cross-category fallback synonyms check
		foreach ( self::$category_synonyms_map as $c => $map ) {
			if ( isset( $map[ $k_clean ] ) ) {
				return $map[ $k_clean ];
			}
		}

		// Standardize unknown keys to Title Case
		return ucwords( strtolower( $k ) );
	}

	/**
	 * Accurately resolves canonical CPU socket from CPU model title/text context.
	 *
	 * @param string $title
	 * @return string|null e.g. 'LGA1700', 'AM5', 'LGA1851', 'LGA1200', 'AM4', 'sTR5', 'sTRX4', 'LGA1151'
	 */
	public static function resolve_cpu_socket_from_title( $title ) {
		if ( empty( $title ) || ! is_string( $title ) ) {
			return null;
		}
		$t = strtoupper( $title );

		// 1. Explicit socket mentions
		if ( preg_match( '/\b(AM5|AM4|AM3\+?|LGA\s*1851|LGA\s*1700|LGA\s*1200|LGA\s*1151|LGA\s*2066|LGA\s*1150|LGA\s*1155|sTR5|sTRX4|TR4|SP5|SP3|FCLGA1700|FCLGA1851|FCLGA1200|FCLGA1151)\b/i', $t, $m ) ) {
			$s = strtoupper( str_replace( ' ', '', $m[1] ) );
			if ( strpos( $s, 'FCLGA' ) === 0 ) {
				$s = substr( $s, 2 );
			}
			return $s;
		}

		// 2. Intel Core Ultra 200 series -> LGA1851
		if ( preg_match( '/\b(?:CORE\s+ULTRA\s+[579]\s+2\d{2}[A-Z0-9]*)\b/i', $t ) ) {
			return 'LGA1851';
		}

		// 3. Intel 12th, 13th, 14th Gen -> LGA1700
		if ( preg_match( '/\b(?:I[3579]-?1[234]\d{3}[A-Z0-9]*|1[234]TH\s*GEN)\b/i', $t ) ) {
			return 'LGA1700';
		}

		// 4. Intel 10th, 11th Gen -> LGA1200
		if ( preg_match( '/\b(?:I[3579]-?1[01]\d{3}[A-Z0-9]*|1[01]TH\s*GEN)\b/i', $t ) ) {
			return 'LGA1200';
		}

		// 5. Intel 6th, 7th, 8th, 9th Gen -> LGA1151
		if ( preg_match( '/\b(?:I[3579]-?[6789]\d{3}[A-Z0-9]*|[6789]TH\s*GEN)\b/i', $t ) ) {
			return 'LGA1151';
		}

		// 6. AMD Ryzen 7000, 8000, 9000 series -> AM5
		if ( preg_match( '/\b(?:RYZEN\s+[3579]\s+[789]\d{3}[A-Z0-9]*|RYZEN\s+AI\s+9\s+\w+)\b/i', $t ) ) {
			return 'AM5';
		}

		// 7. AMD Ryzen 1000, 2000, 3000, 4000, 5000 series -> AM4
		if ( preg_match( '/\b(?:RYZEN\s+[3579]\s+[12345]\d{3}[A-Z0-9]*)\b/i', $t ) ) {
			return 'AM4';
		}

		// 8. Threadripper 7000 -> sTR5
		if ( preg_match( '/\bTHREADRIPPER\s+(?:PRO\s+)?7\d{3}\b/i', $t ) ) {
			return 'sTR5';
		}

		// 9. Threadripper 3000 -> sTRX4
		if ( preg_match( '/\bTHREADRIPPER\s+(?:PRO\s+)?3\d{3}\b/i', $t ) ) {
			return 'sTRX4';
		}

		// 10. Motherboard Chipset Context (e.g. H410M -> LGA1200, B650 -> AM5, A520 -> AM4, Z790 -> LGA1700)
		if ( preg_match( '/\b(X870E|X870|X670E|X670|B850|B840|B650E|B650|A620|X570S|X570|B550|A520|X470|B450|X370|B350|A320|WRX90|TRX50|WRX80|TRX40|X399|Z890|B860|H810|Z790|B760|H770|Z690|B660|H670|H610|Z590|B560|H570|H510|Z490|B460|H470|H410|Z390|Z370|B365|B360|H370|H310|Z270|H270|B250|Z170|H170|B150|H110|X299|X99|Z97|H97|Z87|H87|B85|H81)(?:\b|[MIAE\-])/i', $t, $cm ) ) {
			$chip = strtoupper( $cm[1] );
			if ( isset( self::$motherboard_chipsets[ $chip ] ) ) {
				return self::$motherboard_chipsets[ $chip ]['socket'];
			}
		}

		return null;
	}

	/**
	 * Accurately resolves canonical PSU Wattage from product title/model context.
	 *
	 * @param string $title
	 * @return string|null e.g. '750W', '850W', '1000W', '650W'
	 */
	public static function resolve_psu_wattage_from_title( $title ) {
		if ( empty( $title ) || ! is_string( $title ) ) {
			return null;
		}

		// 1. Explicit wattage pattern in title (e.g. 750W, 750 Watt, 750 Watts, 750-Watt, 750 WW, 750 WattsW)
		if ( preg_match( '/(\d{3,4})\s*(?:W|Watt|Watts|WW|WattsW)\b/i', $title, $m ) ) {
			$w_num = intval( $m[1] );
			if ( $w_num >= 200 && $w_num <= 3500 ) {
				return $w_num . 'W';
			}
		}

		// 2. Embedded in model code (e.g. RM750e -> 750, A850GL -> 850, MWE650 -> 650, PK550D -> 550)
		if ( preg_match( '/\b[A-Za-z]*(\d{3,4})[A-Za-z0-9]*\b/', $title, $m ) ) {
			$w_num = intval( $m[1] );
			$standard_wattages = array( 250, 300, 350, 400, 450, 500, 550, 600, 650, 700, 750, 800, 850, 1000, 1050, 1200, 1250, 1300, 1500, 1550, 1600, 1650, 2000, 3000 );
			if ( in_array( $w_num, $standard_wattages, true ) ) {
				return $w_num . 'W';
			}
		}

		return null;
	}

	/**
	 * Strictly sanitizes and validates an extracted specification value against domain rules,
	 * rejecting package sizes (e.g. "45.0 mm x 37.5 mm"), scalability codes ("1P"), and duplicate Wattage units ("1250 WW", "450 WattsW").
	 *
	 * @param string $key Canonical or raw spec key.
	 * @param string $val Raw spec value.
	 * @param string $category Hardware category slug.
	 * @param string $text_context Full title or model context for inference.
	 * @return string|null Cleaned value or null if garbage/invalid.
	 */
	public static function sanitize_and_validate_spec_value( $key, $val, $category = '', $text_context = '' ) {
		if ( empty( $val ) || ! is_scalar( $val ) ) {
			return null;
		}
		$v = trim( (string) $val );
		$k_lower = strtolower( trim( (string) $key ) );
		$v_lower = strtolower( $v );
		$cat = self::normalize_category_slug( $category );

		// 1. Strict Socket Validation
		if ( in_array( $k_lower, array( 'socket', 'cpu socket', 'socket support', 'sockets supported' ), true ) ) {
			// Reject pure dimensions (e.g. "45.0 mm x 37.5 mm", "37.5 x 37.5 mm", "45 x 37.5")
			if ( preg_match( '/\d+(?:\.\d+)?\s*mm\s*x\s*\d+(?:\.\d+)?\s*mm/i', $v_lower ) ||
			     preg_match( '/^\d+(?:\.\d+)?\s*x\s*\d+(?:\.\d+)?\s*(?:mm|cm|inch)?$/i', $v_lower ) ) {
				return self::resolve_cpu_socket_from_title( $text_context );
			}

			// Reject scalability/packaging codes ("1P", "2P", "1S", "2S", "Single", "Dual", "Box", "Tray", "OEM", "Embedded", "FCBGA...")
			if ( preg_match( '/^[1248][PS]$/i', $v ) || in_array( $v_lower, array( 'single', 'dual', 'box', 'tray', 'oem', 'w/o cooler', 'with cooler', 'embedded', 'fcbga' ), true ) ) {
				return self::resolve_cpu_socket_from_title( $text_context );
			}

			// Extract genuine socket from string (e.g. "FCLGA1700" -> "LGA1700", "Socket AM5 (LGA 1718)" -> "AM5")
			if ( preg_match( '/\b(AM5|AM4|AM3\+?|LGA\s*1851|LGA\s*1700|LGA\s*1200|LGA\s*1151|LGA\s*2066|LGA\s*1150|LGA\s*1155|sTR5|sTRX4|TR4|SP5|SP3|FCLGA1700|FCLGA1851|FCLGA1200|FCLGA1151)\b/i', $v, $sm ) ) {
				$s = strtoupper( str_replace( ' ', '', $sm[1] ) );
				if ( strpos( $s, 'FCLGA' ) === 0 ) {
					$s = substr( $s, 2 );
				}
				return $s;
			}

			// For coolers supporting multiple sockets (e.g. "LGA1700 / LGA1200 / AM5 / AM4")
			if ( $cat === 'cooler' && preg_match( '/(LGA|AM\d)/i', $v ) ) {
				return $v;
			}

			// Fallback to model context inference
			return self::resolve_cpu_socket_from_title( $text_context );
		}

		// 2. Number of Cores
		if ( $k_lower === 'number of cores' || $k_lower === 'cores' ) {
			if ( preg_match( '/(\d+)\s*(?:cores?|p-cores?|\(|$)/i', $v, $m ) ) {
				return $m[1];
			}
			if ( preg_match( '/^\d+$/', $v ) ) {
				return $v;
			}
			return null;
		}

		// 3. Number of Threads
		if ( $k_lower === 'number of threads' || $k_lower === 'threads' ) {
			if ( preg_match( '/(\d+)\s*(?:threads?|$)/i', $v, $m ) ) {
				return $m[1];
			}
			if ( preg_match( '/^\d+$/', $v ) ) {
				return $v;
			}
			return null;
		}

		// 4. Frequency / Turbo Clock
		if ( in_array( $k_lower, array( 'frequency', 'turbo clock', 'base clock', 'boost clock', 'game clock', 'memory speed' ), true ) ) {
			if ( preg_match( '/(\d+(?:\.\d+)?)\s*(?:GHz|G)/i', $v, $m ) ) {
				return $m[1] . ' GHz';
			}
			if ( preg_match( '/(\d{3,5})\s*(?:MHz|MT\/s|M)/i', $v, $m ) ) {
				if ( intval( $m[1] ) >= 1000 && in_array( $k_lower, array( 'frequency', 'turbo clock', 'base clock', 'boost clock', 'game clock' ), true ) ) {
					$ghz = round( floatval( $m[1] ) / 1000.0, 2 );
					return $ghz . ' GHz';
				}
				return $m[1] . ' MHz';
			}
			if ( preg_match( '/^\d+(?:\.\d+)?$/', $v ) ) {
				return floatval( $v ) < 10.0 ? $v . ' GHz' : $v . ' MHz';
			}
			return null;
		}

		// 5. TDP / PPT (Strict single unit e.g. '58W', '65W', '125W', never '58 WW' or '58 W')
		if ( in_array( $k_lower, array( 'tdp', 'ppt' ), true ) ) {
			if ( preg_match( '/(\d+(?:\.\d+)?)\s*(?:W(?:att(?:s)?)?|WW|WattsW|W\s*W)?/i', $v, $m ) ) {
				return $m[1] . 'W';
			}
			return null;
		}

		// 6. Memory Size / Capacity (GPU & RAM & Storage)
		if ( in_array( $k_lower, array( 'memory size', 'capacity' ), true ) ) {
			if ( $cat === 'gpu' || $cat === 'ram' ) {
				if ( preg_match( '/(\d+)\s*(?:GB|G)\b/i', $v, $m ) ) {
					return $m[1] . ' GB';
				}
				if ( preg_match( '/^\d+$/', $v ) ) {
					return $v . ' GB';
				}
			} elseif ( $cat === 'storage' ) {
				if ( preg_match( '/(\d+)\s*(TB|GB)\b/i', $v, $m ) ) {
					return $m[1] . ' ' . strtoupper( $m[2] );
				}
			}
		}

		// 7. Suggested PSU (GPU category: '750 W')
		if ( $k_lower === 'suggested psu' ) {
			if ( preg_match( '/(\d{3,4})\s*(?:W(?:att(?:s)?)?|WW|WattsW|W\s*W)?/i', $v, $m ) ) {
				return $m[1] . ' W';
			}
			if ( preg_match( '/^\d{3,4}$/', $v ) ) {
				return $v . ' W';
			}
		}

		// 8. PSU Wattage (Strict single 'W' e.g. '450W', '550W', '750W', '1250W', never 'WW' or 'WattsW')
		if ( $k_lower === 'wattage' ) {
			if ( preg_match( '/(\d{3,4})\s*(?:W(?:att(?:s)?)?|WW|WattsW|W\s*W)?/i', $v, $m ) ) {
				$w_num = intval( $m[1] );
				if ( $w_num >= 200 && $w_num <= 3500 ) {
					return $w_num . 'W';
				}
			}
			return self::resolve_psu_wattage_from_title( $text_context );
		}

		// 9. Motherboard Form Factor
		if ( $k_lower === 'form factor' && $cat === 'motherboard' ) {
			if ( preg_match( '/\b(E-ATX|Extended ATX|ATX|Micro-ATX|Micro ATX|mATX|Mini-ITX|Mini ITX|ITX)\b/i', $v, $m ) ) {
				return strtoupper( str_replace( ' ', '-', $m[1] ) );
			}
		}

		// 10. Fan Size (e.g. '120 mm', '140 mm')
		if ( in_array( $k_lower, array( 'fan size', 'fan dimensions', 'dimensions' ), true ) && ( $cat === 'case_fan' || $cat === 'cooler' ) ) {
			if ( preg_match( '/\b(120|140|80|92|200)\s*(?:mm)?\b/i', $v, $m ) ) {
				return $m[1] . ' mm';
			}
		}

		// 11. PWM Support / Control
		if ( in_array( $k_lower, array( 'pwm support', 'pwm control', 'pwm' ), true ) ) {
			if ( in_array( $v_lower, array( 'pwm', 'yes', 'true', '1', 'supported', 'pwm control', '4-pin pwm', '4 pin pwm' ), true ) ) {
				return 'Yes';
			}
			if ( in_array( $v_lower, array( 'no', 'false', '0', 'none', '3-pin', '3 pin', 'non-pwm' ), true ) ) {
				return 'No';
			}
		}

		return $v;
	}

	/**
	 * Scans components in database and fixes any corrupted socket or wattage values
	 * (e.g. "45.0 mm x 37.5 mm", "1P", "1250 WW", "450 WattsW"), updating both DB specs_json and postmeta.
	 *
	 * @return int Number of components repaired.
	 */
	public static function sanitize_database_spec_values() {
		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );
		if ( empty( $comp_table ) || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}

		$rows = $wpdb->get_results( "SELECT * FROM {$comp_table} WHERE category IN ('cpu', 'motherboard', 'cooler', 'psu')", \ARRAY_A );
		if ( empty( $rows ) ) {
			return 0;
		}

		$repaired = 0;
		foreach ( $rows as $row ) {
			$comp = new Component( $row );
			$specs = $comp->get_specs() ?: array();
			$title = trim( $comp->brand . ' ' . $comp->model_name );
			$changed = false;

			// 1. Socket Sanitization
			if ( isset( $specs['Socket'] ) ) {
				$cur_sock = (string) $specs['Socket'];
				$clean_sock = self::sanitize_and_validate_spec_value( 'Socket', $cur_sock, $comp->category, $title );

				if ( $clean_sock !== $cur_sock ) {
					if ( ! empty( $clean_sock ) ) {
						$specs['Socket'] = $clean_sock;
					} else {
						unset( $specs['Socket'] );
					}
					$changed = true;
				}
			} elseif ( $comp->category === 'cpu' || $comp->category === 'motherboard' ) {
				$inferred = self::resolve_cpu_socket_from_title( $title );
				if ( ! empty( $inferred ) ) {
					$specs['Socket'] = $inferred;
					$changed = true;
				}
			}

			// 2. PSU Wattage Sanitization
			if ( $comp->category === 'psu' ) {
				if ( isset( $specs['Wattage'] ) ) {
					$cur_watt = (string) $specs['Wattage'];
					$clean_watt = self::sanitize_and_validate_spec_value( 'Wattage', $cur_watt, 'psu', $title );
					if ( $clean_watt !== $cur_watt ) {
						if ( ! empty( $clean_watt ) ) {
							$specs['Wattage'] = $clean_watt;
						} else {
							unset( $specs['Wattage'] );
						}
						$changed = true;
					}
				} else {
					$inferred_w = self::resolve_psu_wattage_from_title( $title );
					if ( ! empty( $inferred_w ) ) {
						$specs['Wattage'] = $inferred_w;
						$changed = true;
					}
				}
			}

			// 3. CPU TDP Sanitization (e.g. "58 WW", "58 W", "58Watts" -> "58W")
			if ( $comp->category === 'cpu' && isset( $specs['TDP'] ) ) {
				$cur_tdp = (string) $specs['TDP'];
				if ( preg_match( '/(\d+)\s*(?:W(?:att(?:s)?)?|WW|WattsW|W\s*W)?/i', $cur_tdp, $tm ) ) {
					$clean_tdp = $tm[1] . 'W';
					if ( $clean_tdp !== $cur_tdp ) {
						$specs['TDP'] = $clean_tdp;
						$changed = true;
					}
				}
			}

			// 3. Motherboard Platform, Socket & Chipset Sanitization
			if ( $comp->category === 'motherboard' ) {
				$mb_chip = null;
				if ( preg_match( '/\b(X870E|X870|X670E|X670|B850|B840|B650E|B650|A620|X570S|X570|B550|A520|X470|B450|X370|B350|A320|WRX90|TRX50|WRX80|TRX40|X399|Z890|B860|H810|Z790|B760|H770|Z690|B660|H670|H610|Z590|B560|H570|H510|Z490|B460|H470|H410|Z390|Z370|B365|B360|H370|H310|Z270|H270|B250|Z170|H170|B150|H110|X299|X99|Z97|H97|Z87|H87|B85|H81)(?:\b|[MIAE\-])/i', $title, $cm ) ) {
					$mb_chip = strtoupper( $cm[1] );
				}

				if ( $mb_chip && isset( self::$motherboard_chipsets[ $mb_chip ] ) ) {
					$mb_info = self::$motherboard_chipsets[ $mb_chip ];
					if ( empty( $specs['Chipset'] ) || $specs['Chipset'] !== $mb_chip ) {
						$specs['Chipset'] = $mb_chip;
						$changed = true;
					}
					if ( empty( $specs['Platform'] ) || $specs['Platform'] !== $mb_info['platform'] ) {
						$specs['Platform'] = $mb_info['platform'];
						$changed = true;
					}
					if ( empty( $specs['Socket'] ) || $specs['Socket'] !== $mb_info['socket'] ) {
						$specs['Socket'] = $mb_info['socket'];
						$changed = true;
					}
					if ( empty( $specs['Cpu Type'] ) ) {
						$specs['Cpu Type'] = ( $mb_info['platform'] === 'Intel' ) ? 'Intel Core' : 'AMD Ryzen';
						$changed = true;
					}
					if ( ! empty( $mb_info['mem'] ) && empty( $specs['Supported Memory Type'] ) ) {
						$specs['Supported Memory Type'] = $mb_info['mem'];
						$changed = true;
					}
				}

				// Check for incorrectly merged vendor prices with conflicting chipsets (e.g. A520 price on H410 board)
				$prices = $comp->get_prices();
				foreach ( $prices as $p ) {
					if ( ! empty( $p->vendor_product_title ) && $mb_chip ) {
						if ( preg_match( '/\b(X870E|X870|X670E|X670|B850|B840|B650E|B650|A620|X570S|X570|B550|A520|X470|B450|X370|B350|A320|WRX90|TRX50|WRX80|TRX40|X399|Z890|B860|H810|Z790|B760|H770|Z690|B660|H670|H610|Z590|B560|H570|H510|Z490|B460|H470|H410|Z390|Z370|B365|B360|H370|H310|Z270|H270|B250|Z170|H170|B150|H110|X299|X99|Z97|H97|Z87|H87|B85|H81)(?:\b|[MIAE\-])/i', $p->vendor_product_title, $vcm ) ) {
							$v_chip = strtoupper( $vcm[1] );
							if ( $v_chip !== $mb_chip ) {
								// Conflicting chipset listing! Automatically unmerge into separate component
								Matching_Engine::unmerge_vendor_price( $p->id );
								$changed = true;
							}
						}
					}
				}
			}

			if ( $changed ) {
				self::sync_post_specs( $comp, $specs );
				$repaired++;
			}
		}

		return $repaired;
	}

	public static function sanitize_database_cpu_sockets() {
		return self::sanitize_database_spec_values();
	}

	/**
	 * Synchronize component specifications across all backend destinations:
	 * 1. HWsync canonical components table (wp_hwsync_components)
	 * 2. Native PCSpecs theme components table (wp_pc_components) if installed
	 * 3. Linked WordPress post and individual postmeta attributes
	 *
	 * @param Component|object $component Component instance or database row.
	 * @param array|null $specs Normalized specifications array.
	 * @return bool True on success.
	 */
	public static function sync_post_specs( $component, $specs = array() ) {
		global $wpdb;
		if ( ! $component || empty( $component->id ) ) {
			return false;
		}

		if ( ! is_array( $specs ) ) {
			$specs = is_string( $specs ) ? json_decode( $specs, true ) : array();
		}
		$specs = is_array( $specs ) ? $specs : array();

		// 1. Direct update to HWsync components table
		$comp_table = Database::get_table_name( 'components' );
		$wpdb->update(
			$comp_table,
			array( 'specs_json' => ! empty( $specs ) ? wp_json_encode( $specs ) : null ),
			array( 'id' => $component->id )
		);
		$component->specs_json = $specs;

		// 2. Direct update to native PCSpecs theme components table (wp_pc_components) if present
		$pc_comp_table = ( isset( $wpdb->prefix ) ? $wpdb->prefix : 'wp_' ) . 'pc_components';
		$has_pc_table = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $pc_comp_table ) ) === $pc_comp_table );
		if ( $has_pc_table ) {
			$pc_update_data = array(
				'specs'      => ! empty( $specs ) ? wp_json_encode( $specs ) : null,
				'specs_json' => ! empty( $specs ) ? wp_json_encode( $specs ) : null,
			);
			if ( ! empty( $specs['Socket'] ) ) {
				$pc_update_data['socket'] = $specs['Socket'];
			}
			if ( ! empty( $specs['Wattage'] ) ) {
				$pc_update_data['wattage'] = $specs['Wattage'];
			}
			$wpdb->update( $pc_comp_table, $pc_update_data, array( 'id' => $component->id ) );
			if ( ! empty( $component->model_name ) ) {
				$wpdb->update( $pc_comp_table, $pc_update_data, array( 'model_name' => $component->model_name ) );
			}
		}

		// 3. Resolve WordPress Post ID (from $component->wp_post_id, postmeta search, or title)
		$post_id = ! empty( $component->wp_post_id ) ? intval( $component->wp_post_id ) : 0;
		if ( ! $post_id && isset( $wpdb->postmeta ) ) {
			$post_id = intval( $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE (meta_key = '_pcspecs_component_id' OR meta_key = '_hwsync_component_id') AND meta_value = %d LIMIT 1",
				$component->id
			) ) );
		}
		if ( ! $post_id && function_exists( 'get_post' ) ) {
			$maybe_post = get_post( $component->id );
			if ( $maybe_post && in_array( $maybe_post->post_type, array( 'pc_component', 'pcspecs_component', 'product', 'component', 'post' ), true ) ) {
				$post_id = $maybe_post->ID;
			}
		}
		if ( ! $post_id && isset( $wpdb->posts ) && ! empty( $component->brand ) && ! empty( $component->model_name ) ) {
			$full_title = trim( $component->brand . ' ' . $component->model_name );
			$post_id = intval( $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_status NOT IN ('trash', 'auto-draft') LIMIT 1",
				$full_title
			) ) );
		}

		// 4. Update WordPress Post Meta
		if ( $post_id > 0 && function_exists( 'update_post_meta' ) ) {
			if ( ! empty( $specs ) ) {
				update_post_meta( $post_id, '_pcspecs_specs', $specs );
				update_post_meta( $post_id, '_hwsync_specs', $specs );
				update_post_meta( $post_id, '_pcspecs_component_id', $component->id );
				update_post_meta( $post_id, '_hwsync_component_id', $component->id );

				// Socket
				if ( ! empty( $specs['Socket'] ) ) {
					update_post_meta( $post_id, '_pcspecs_socket', $specs['Socket'] );
					update_post_meta( $post_id, '_hwsync_socket', $specs['Socket'] );
				} elseif ( ! empty( $specs['Socket Support'] ) ) {
					update_post_meta( $post_id, '_pcspecs_socket', $specs['Socket Support'] );
					update_post_meta( $post_id, '_hwsync_socket', $specs['Socket Support'] );
				}

				// Wattage
				if ( ! empty( $specs['Wattage'] ) ) {
					update_post_meta( $post_id, '_pcspecs_wattage', $specs['Wattage'] );
					update_post_meta( $post_id, '_hwsync_wattage', $specs['Wattage'] );
				}

				// Cores & Threads
				if ( ! empty( $specs['Number of Cores'] ) ) {
					update_post_meta( $post_id, '_pcspecs_cores', $specs['Number of Cores'] );
					update_post_meta( $post_id, '_hwsync_cores', $specs['Number of Cores'] );
				}
				if ( ! empty( $specs['Number of Threads'] ) ) {
					update_post_meta( $post_id, '_pcspecs_threads', $specs['Number of Threads'] );
					update_post_meta( $post_id, '_hwsync_threads', $specs['Number of Threads'] );
				}

				// Frequency & Boost Clock
				if ( ! empty( $specs['Frequency'] ) ) {
					update_post_meta( $post_id, '_pcspecs_frequency', $specs['Frequency'] );
					update_post_meta( $post_id, '_hwsync_frequency', $specs['Frequency'] );
				}
				if ( ! empty( $specs['Turbo Clock'] ) ) {
					update_post_meta( $post_id, '_pcspecs_turbo_clock', $specs['Turbo Clock'] );
					update_post_meta( $post_id, '_hwsync_turbo_clock', $specs['Turbo Clock'] );
				} elseif ( ! empty( $specs['Boost Clock'] ) ) {
					update_post_meta( $post_id, '_pcspecs_boost_clock', $specs['Boost Clock'] );
					update_post_meta( $post_id, '_hwsync_boost_clock', $specs['Boost Clock'] );
				}

				// Chipset
				if ( ! empty( $specs['Chipset'] ) ) {
					update_post_meta( $post_id, '_pcspecs_chipset', $specs['Chipset'] );
					update_post_meta( $post_id, '_hwsync_chipset', $specs['Chipset'] );
				}

				// Form Factor
				if ( ! empty( $specs['Form Factor'] ) ) {
					update_post_meta( $post_id, '_pcspecs_form_factor', $specs['Form Factor'] );
					update_post_meta( $post_id, '_hwsync_form_factor', $specs['Form Factor'] );
				} elseif ( ! empty( $specs['Cabinet Size'] ) ) {
					update_post_meta( $post_id, '_pcspecs_form_factor', $specs['Cabinet Size'] );
					update_post_meta( $post_id, '_hwsync_form_factor', $specs['Cabinet Size'] );
				}

				// Memory Type & Capacity & Speed
				if ( ! empty( $specs['Memory Type'] ) ) {
					update_post_meta( $post_id, '_pcspecs_memory_type', $specs['Memory Type'] );
					update_post_meta( $post_id, '_hwsync_memory_type', $specs['Memory Type'] );
				} elseif ( ! empty( $specs['Supported Memory Type'] ) ) {
					update_post_meta( $post_id, '_pcspecs_memory_type', $specs['Supported Memory Type'] );
					update_post_meta( $post_id, '_hwsync_memory_type', $specs['Supported Memory Type'] );
				}
				if ( ! empty( $specs['Capacity'] ) ) {
					update_post_meta( $post_id, '_pcspecs_capacity', $specs['Capacity'] );
					update_post_meta( $post_id, '_hwsync_capacity', $specs['Capacity'] );
				} elseif ( ! empty( $specs['Memory Size'] ) ) {
					update_post_meta( $post_id, '_pcspecs_capacity', $specs['Memory Size'] );
					update_post_meta( $post_id, '_hwsync_capacity', $specs['Memory Size'] );
				}
				if ( ! empty( $specs['Speed'] ) ) {
					update_post_meta( $post_id, '_pcspecs_speed', $specs['Speed'] );
					update_post_meta( $post_id, '_hwsync_speed', $specs['Speed'] );
				} elseif ( ! empty( $specs['Rated Speed'] ) ) {
					update_post_meta( $post_id, '_pcspecs_speed', $specs['Rated Speed'] );
					update_post_meta( $post_id, '_hwsync_speed', $specs['Rated Speed'] );
				}

				// TDP & Suggested PSU
				if ( ! empty( $specs['TDP'] ) ) {
					update_post_meta( $post_id, '_pcspecs_tdp', $specs['TDP'] );
					update_post_meta( $post_id, '_hwsync_tdp', $specs['TDP'] );
				}
				if ( ! empty( $specs['Suggested PSU'] ) ) {
					update_post_meta( $post_id, '_pcspecs_suggested_psu', $specs['Suggested PSU'] );
					update_post_meta( $post_id, '_hwsync_suggested_psu', $specs['Suggested PSU'] );
				}

				// Link wp_post_id back to component if it was missing
				if ( empty( $component->wp_post_id ) ) {
					$component->wp_post_id = $post_id;
					$wpdb->update( $comp_table, array( 'wp_post_id' => $post_id ), array( 'id' => $component->id ) );
				}
			} else {
				delete_post_meta( $post_id, '_pcspecs_specs' );
				delete_post_meta( $post_id, '_hwsync_specs' );
			}
		}

		return true;
	}

	/**
	 * Run manual specs synchronization for existing canonical components in DB.
	 * Visits product pages across ALL linked retailers, aggregates missing specifications,
	 * skips components that already have complete specifications, and updates posts.
	 *
	 * @param array $options Options array: 'category', 'component_id', 'limit', 'offset', 'force'.
	 * @param callable|null $logger Progress callback logger.
	 * @return array Sync report.
	 */
	public function run_specs_sync( $options = array(), $logger = null ) {
		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );

		$category     = isset( $options['category'] ) ? sanitize_text_field( $options['category'] ) : 'all';
		$component_id = isset( $options['component_id'] ) ? intval( $options['component_id'] ) : 0;
		$limit        = isset( $options['limit'] ) ? intval( $options['limit'] ) : 100;
		$offset       = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
		$force        = ! empty( $options['force'] );

		$this->emit( $logger, 'info', "Starting Technical Specifications Multi-Vendor Aggregator..." );

		// 1. Database Sockets Pre-Sanitization & Repair on Initial Step
		if ( $offset === 0 ) {
			$repaired = self::sanitize_database_cpu_sockets();
			if ( $repaired > 0 ) {
				$this->emit( $logger, 'info', "Sanitized & Repaired {$repaired} component socket records in database." );
			}
		}

		$where_clauses = array( "1=1" );
		if ( $component_id > 0 ) {
			$where_clauses[] = $wpdb->prepare( "id = %d", $component_id );
		} elseif ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$components_raw = $wpdb->get_results( "SELECT * FROM {$comp_table} WHERE {$where_sql} ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}", \ARRAY_A );

		if ( empty( $components_raw ) ) {
			$this->emit( $logger, 'warning', "No components found in database matching criteria." );
			return array( 'total' => 0, 'updated' => 0, 'skipped' => 0 );
		}

		$report = array(
			'total_components' => count( $components_raw ),
			'specs_updated'    => 0,
			'skipped'          => 0,
			'posts_refreshed'  => 0,
			'errors'           => array(),
		);

		$this->emit( $logger, 'info', "Found " . count( $components_raw ) . " canonical components in DB. Checking specification completeness across retailer sources..." );

		foreach ( $components_raw as $c_row ) {
			$component = new Component( $c_row );
			$existing_specs = $component->get_specs() ?: array();

			// Skip if already completely populated with all category specs and not forcing re-sync
			$missing_keys = self::get_missing_specs( $existing_specs, $component->category );
			if ( empty( $missing_keys ) && ! $force ) {
				$report['skipped']++;
				$this->emit( $logger, 'info', "[SKIPPED] Component #{$component->id} [{$component->brand} {$component->model_name}] already has all specifications." );
				continue;
			}

			$this->emit( $logger, 'info', "Syncing specs for Component #{$component->id}: [{$component->brand} {$component->model_name}] ({$component->category}). Missing " . count( $missing_keys ) . " specs..." );

			$prices = $component->get_prices();
			if ( empty( $prices ) ) {
				$this->emit( $logger, 'debug', "Component #{$component->id} has no linked vendor price listings. Skipping." );
				continue;
			}

			$collected_text = $component->brand . ' ' . $component->model_name . ' ' . ( $component->mpn ?: '' ) . ' ' . ( $component->sku ?: '' );
			$current_specs = $existing_specs;

			// Multi-vendor cascading sync: scan vendor 1; if complete, stop; else scan next vendor only for missing specs
			foreach ( $prices as $p ) {
				if ( empty( $p->product_url ) ) {
					continue;
				}

				$missing_keys = self::get_missing_specs( $current_specs, $component->category );
				if ( empty( $missing_keys ) ) {
					$this->emit( $logger, 'debug', "All required specifications gathered. Skipping remaining vendor stores." );
					break;
				}

				$vendor = Vendor::find_by_id( $p->vendor_id );
				$vendor_name = $vendor ? $vendor->vendor_name : 'Retailer';
				$vendor_slug = $vendor ? $vendor->vendor_slug : '';

				$fetched_specs = $this->fetch_specs_from_product_url( $p->product_url, $vendor_slug, $component->category );
				if ( ! empty( $fetched_specs ) ) {
					$clean_fetched = self::merge_and_clean_specs( $component->category, $fetched_specs, array(), $collected_text );
					$new_attrs = 0;

					foreach ( $missing_keys as $m_key ) {
						if ( isset( $clean_fetched[ $m_key ] ) && trim( (string) $clean_fetched[ $m_key ] ) !== '' ) {
							$current_specs[ $m_key ] = $clean_fetched[ $m_key ];
							$new_attrs++;
						}
					}

					$this->emit( $logger, 'info', "Extracted " . count( $fetched_specs ) . " raw specs ({$new_attrs} missing attributes filled) from {$vendor_name}." );
				}

				// Re-check missing keys after this vendor
				$missing_keys = self::get_missing_specs( $current_specs, $component->category );
				if ( empty( $missing_keys ) ) {
					$this->emit( $logger, 'success', "All specifications now fully obtained from {$vendor_name}." );
					break;
				}
			}

			// Clean, normalize and merge specs
			$merged_specs = self::merge_and_clean_specs( $component->category, $current_specs, array(), $collected_text );

			if ( ! empty( $merged_specs ) ) {
				self::sync_post_specs( $component, $merged_specs );
				$report['posts_refreshed']++;
				$report['specs_updated']++;
				$this->emit( $logger, 'success', "Specs Saved for #{$component->id} [{$component->brand} {$component->model_name}]: " . self::format_specs_summary( $merged_specs ) );
			} else {
				$this->emit( $logger, 'warning', "No valid category specifications found for Component #{$component->id}." );
			}
		}

		$this->emit( $logger, 'success', "Technical Specifications Sync complete. Updated specs for {$report['specs_updated']} components (Skipped {$report['skipped']} already complete)." );
		return $report;
	}

	/**
	 * Sync specifications in fast chunked mode (for AJAX step progression).
	 */
	public function sync_specs_chunk( $options = array(), $logger = null ) {
		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );

		$category = isset( $options['category'] ) ? sanitize_text_field( $options['category'] ) : 'all';
		$offset   = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
		$limit    = isset( $options['limit'] ) ? intval( $options['limit'] ) : 2;
		$force    = ! empty( $options['force'] );

		$where_clauses = array( "1=1" );
		if ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}
		$where_sql = implode( ' AND ', $where_clauses );

		$total_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$comp_table} WHERE {$where_sql}" ) );
		$components_raw = $wpdb->get_results( "SELECT * FROM {$comp_table} WHERE {$where_sql} ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}", \ARRAY_A );

		$logs = array();
		$updated = 0;
		$skipped = 0;

		if ( $offset === 0 ) {
			$repaired = self::sanitize_database_spec_values();
			if ( $repaired > 0 ) {
				$logs[] = array(
					'level'   => 'info',
					'message' => "Sanitized & Repaired {$repaired} component specification records (Sockets & Wattages) in database.",
				);
			}
		}

		foreach ( $components_raw as $c_row ) {
			$component = new Component( $c_row );
			$existing_specs = $component->get_specs() ?: array();

			// Skip if already complete and not force re-syncing
			$missing_keys = self::get_missing_specs( $existing_specs, $component->category );
			if ( empty( $missing_keys ) && ! $force ) {
				$skipped++;
				$logs[] = array(
					'level'   => 'info',
					'message' => "[SKIPPED] Component #{$component->id} [{$component->brand} {$component->model_name}] already has all specifications.",
				);
				continue;
			}

			$prices = $component->get_prices();
			$collected_text = $component->brand . ' ' . $component->model_name . ' ' . ( $component->mpn ?: '' ) . ' ' . ( $component->sku ?: '' );
			$current_specs = $existing_specs;

			// Multi-vendor cascading sync
			foreach ( $prices as $p ) {
				if ( empty( $p->product_url ) ) {
					continue;
				}

				$missing_keys = self::get_missing_specs( $current_specs, $component->category );
				if ( empty( $missing_keys ) ) {
					break;
				}

				$vendor = Vendor::find_by_id( $p->vendor_id );
				$vendor_name = $vendor ? $vendor->vendor_name : 'Retailer';
				$vendor_slug = $vendor ? $vendor->vendor_slug : '';

				$fetched = $this->fetch_specs_from_product_url( $p->product_url, $vendor_slug, $component->category );
				if ( ! empty( $fetched ) ) {
					$clean_fetched = self::merge_and_clean_specs( $component->category, $fetched, array(), $collected_text );
					$new_count = 0;

					foreach ( $missing_keys as $m_key ) {
						if ( isset( $clean_fetched[ $m_key ] ) && trim( (string) $clean_fetched[ $m_key ] ) !== '' ) {
							$current_specs[ $m_key ] = $clean_fetched[ $m_key ];
							$new_count++;
						}
					}

					$logs[] = array(
						'level'   => 'match',
						'message' => "[{$component->brand} {$component->model_name}] Extracted " . count( $fetched ) . " specs ({$new_count} missing attributes filled) from {$vendor_name}.",
					);
				}

				$missing_keys = self::get_missing_specs( $current_specs, $component->category );
				if ( empty( $missing_keys ) ) {
					$logs[] = array(
						'level'   => 'success',
						'message' => "[{$component->brand} {$component->model_name}] All specifications fully obtained. Done!",
					);
					break;
				}
			}

			$merged_specs = self::merge_and_clean_specs( $component->category, $current_specs, array(), $collected_text );

			if ( ! empty( $merged_specs ) ) {
				self::sync_post_specs( $component, $merged_specs );
				$updated++;
				$logs[] = array(
					'level'   => 'success',
					'message' => "Specs Saved for #{$component->id} [{$component->brand} {$component->model_name}]: " . self::format_specs_summary( $merged_specs ),
				);
			}
		}

		$next_offset = $offset + count( $components_raw );
		$has_more = ( $next_offset < $total_count );

		return array(
			'success'          => true,
			'has_more'         => $has_more,
			'processed'        => count( $components_raw ),
			'updated'          => $updated,
			'skipped'          => $skipped,
			'total_components' => $total_count,
			'next_offset'      => $next_offset,
			'logs'             => $logs,
		);
	}

	/**
	 * Fetch and extract specifications section from a vendor's product page URL.
	 *
	 * @param string $url
	 * @param string $vendor_slug
	 * @param string $category
	 * @return array Key-value dictionary of clean specs.
	 */
	public function fetch_specs_from_product_url( $url, $vendor_slug, $category = '' ) {
		$specs = array();
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $specs;
		}

		// Handle Shopify stores (EliteHubs) via product JSON
		if ( $vendor_slug === 'elitehubs' || strpos( $url, 'elitehubs.com' ) !== false ) {
			$path = parse_url( $url, PHP_URL_PATH );
			if ( preg_match( '#/products/([^/?]+)#', $path, $m ) ) {
				$json_url = 'https://elitehubs.com/products/' . $m[1] . '.json';
				$json_res = $this->make_http_request( $json_url, array( 'Accept' => 'application/json' ) );
				if ( ! empty( $json_res['body'] ) ) {
					$data = json_decode( $json_res['body'], true );
					if ( isset( $data['product']['body_html'] ) ) {
						$specs = self::parse_html_specs_section( $data['product']['body_html'], $category );
						if ( ! empty( $specs ) ) {
							return $specs;
						}
					}
				}
			}
		}

		// Standard cURL fetch for WooCommerce, OpenCart, Journal 3, Magento, Amazon pages
		$res = $this->make_http_request( $url );
		if ( empty( $res['body'] ) ) {
			return $specs;
		}

		$html = $res['body'];

		// If Cloudflare or anti-bot challenge is triggered, reject immediately
		if ( self::is_bot_challenge_html( $html ) ) {
			return $specs;
		}

		// Strip scripts, styles, noscript, svg, iframes before any section extraction
		$clean_html = preg_replace( '#<(script|style|noscript|svg|iframe)[^>]*>[\s\S]*?</\1>#i', ' ', $html );
		$specs_html = '';

		// Targeted Pattern 0: Amazon product technical details & overview tables
		if ( strpos( $url, 'amazon.in' ) !== false || $vendor_slug === 'amazon-in' || $vendor_slug === 'amazon' ) {
			if ( preg_match_all( '/<(?:div|table)[^>]*(?:id=["\'](?:productOverview_feature_div|productDetails_techSpec_section_1|productDetails_techSpec_section_2|prodDetails|detailBullets_feature_div)["\']|class=["\'][^"\']*(?:prodDetTable|a-keyvalue)[^"\']*)[^>]*>[\s\S]*?<\/(?:div|table)>/i', $clean_html, $am_matches ) ) {
				$specs_html = implode( "\n", $am_matches[0] );
			}
		}

		// Targeted Pattern 1: Dedicated specification tab container
		if ( empty( $specs_html ) && preg_match( '/<(?:div|section|table)[^>]*(?:id=["\']tab-specification["\']|id=["\']tab-specs["\']|class=["\'][^"\']*(?:woocommerce-Tabs-panel--specification|shop_attributes|product-attribute-specs-table|specification)[^"\']*)[^>]*>[\s\S]*?<\/(?:div|section|table)>/i', $clean_html, $sm ) ) {
			$specs_html = $sm[0];
		}
		// Targeted Pattern 2: Attributes table within product container / Amazon tech specs
		elseif ( empty( $specs_html ) && preg_match( '/<table[^>]*(?:class=["\'][^"\']*(?:shop_attributes|table-bordered|table-striped|data-table|table_specifications|prodDetTable)[^"\']*|id=["\'](?:product-attribute-specs-table|productDetails_techSpec_section_1|productDetails_techSpec_section_2)["\'])[^>]*>[\s\S]*?<\/table>/i', $clean_html, $sm ) ) {
			$specs_html = $sm[0];
		}
		// Targeted Pattern 3: Description tab containing definition lists
		elseif ( empty( $specs_html ) && preg_match( '/<div[^>]*id=["\']tab-description["\'][^>]*>[\s\S]*?<\/div>/i', $clean_html, $sm ) ) {
			$specs_html = $sm[0];
		}
		// Targeted Pattern 4: General product details / entry content container
		elseif ( empty( $specs_html ) && preg_match( '/<(?:div|section)[^>]*(?:class=["\'][^"\']*(?:product-description|product-specifications|woocommerce-product-details__short-description|entry-content|productOverview_feature_div)[^"\']*)[^>]*>[\s\S]*?<\/(?:div|section)>/i', $clean_html, $sm ) ) {
			$specs_html = $sm[0];
		}

		if ( empty( $specs_html ) ) {
			return $specs;
		}

		return self::parse_html_specs_section( $specs_html, $category );
	}

	/**
	 * Detect if an HTML payload is a Cloudflare / Bot Management / Amazon CAPTCHA interstitial challenge.
	 *
	 * @param string $html
	 * @return bool
	 */
	public static function is_bot_challenge_html( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return false;
		}
		$markers = array(
			'just a moment...',
			'cf-browser-verification',
			'cf_chl_',
			'__cf_chl_opt',
			'challenge-platform',
			'attention required! | cloudflare',
			'security check | cloudflare',
			'turnstile',
			'ray id:',
			'checking your browser',
			'ddos protection by cloudflare',
			'enable javascript and cookies to continue',
			'enter the characters you see below',
			'type the characters you see in this image',
			'api-services-support@amazon.com',
		);
		$lower = strtolower( $html );
		foreach ( $markers as $m ) {
			if ( strpos( $lower, $m ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse HTML snippet (tables, lists, definition lists, structured text) into clean key-value specs dictionary.
	 *
	 * @param string $html_snippet
	 * @param string $category
	 * @return array
	 */
	public static function parse_html_specs_section( $html_snippet, $category = '' ) {
		$specs = array();
		if ( empty( $html_snippet ) ) {
			return $specs;
		}

		if ( self::is_bot_challenge_html( $html_snippet ) ) {
			return $specs;
		}

		// Strip scripts, styles, noscript, svg, iframes so inline JSON dictionaries are never processed
		$html_snippet = preg_replace( '#<(script|style|noscript|svg|iframe)[^>]*>[\s\S]*?</\1>#i', ' ', $html_snippet );

		$cat = self::normalize_category_slug( $category );
		$allowed = ( ! empty( $cat ) && isset( self::$allowed_specs_by_category[ $cat ] ) ) ? self::$allowed_specs_by_category[ $cat ] : null;

		// 1. Table rows: <tr><td>Key</td><td>Val</td></tr> or <tr><th>Key</th><td>Val</td></tr>
		if ( preg_match_all( '/<tr[^>]*>[\s\S]*?<\/tr>/i', $html_snippet, $rows ) ) {
			foreach ( $rows[0] as $r ) {
				if ( preg_match_all( '/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/i', $r, $cells ) ) {
					if ( count( $cells[1] ) >= 2 ) {
						$k = trim( strip_tags( $cells[1][0] ) );
						$v = trim( strip_tags( $cells[1][1] ) );
						$k = html_entity_decode( $k, ENT_QUOTES, 'UTF-8' );
						$v = html_entity_decode( $v, ENT_QUOTES, 'UTF-8' );
						$k = trim( preg_replace( '/\s+/', ' ', $k ) );
						$v = trim( preg_replace( '/\s+/', ' ', $v ) );

						if ( self::is_valid_spec_pair( $k, $v ) ) {
							$norm_k = self::normalize_spec_key( $k, $category );
							if ( empty( $allowed ) || in_array( $norm_k, $allowed, true ) ) {
								$specs[ $norm_k ] = $v;
							}
						}
					}
				}
			}
		}

		// 2. Definition Lists: <dt>Key</dt><dd>Val</dd>
		if ( preg_match_all( '/<dt[^>]*>([\s\S]*?)<\/dt>\s*<dd[^>]*>([\s\S]*?)<\/dd>/i', $html_snippet, $dls, PREG_SET_ORDER ) ) {
			foreach ( $dls as $dl ) {
				$k = trim( strip_tags( $dl[1] ) );
				$v = trim( strip_tags( $dl[2] ) );
				$k = html_entity_decode( $k, ENT_QUOTES, 'UTF-8' );
				$v = html_entity_decode( $v, ENT_QUOTES, 'UTF-8' );
				$k = trim( preg_replace( '/\s+/', ' ', $k ) );
				$v = trim( preg_replace( '/\s+/', ' ', $v ) );

				if ( self::is_valid_spec_pair( $k, $v ) ) {
					$norm_k = self::normalize_spec_key( $k, $category );
					if ( empty( $allowed ) || in_array( $norm_k, $allowed, true ) ) {
						if ( ! isset( $specs[ $norm_k ] ) ) {
							$specs[ $norm_k ] = $v;
						}
					}
				}
			}
		}

		// 3. List items: <li><strong>Key:</strong> Val</li>
		if ( preg_match_all( '/<li[^>]*>[\s\S]*?<\/li>/i', $html_snippet, $lis ) ) {
			foreach ( $lis[0] as $li ) {
				if ( preg_match( '/<(?:strong|b|span)[^>]*>([^<:]+)[:]?<\/(?:strong|b|span)>[\s:]*([^<]+)/i', $li, $m ) ) {
					$k = trim( strip_tags( $m[1] ) );
					$v = trim( strip_tags( $m[2] ) );
					$k = html_entity_decode( $k, ENT_QUOTES, 'UTF-8' );
					$v = html_entity_decode( $v, ENT_QUOTES, 'UTF-8' );
					$k = trim( preg_replace( '/\s+/', ' ', $k ) );
					$v = trim( preg_replace( '/\s+/', ' ', $v ) );

					if ( self::is_valid_spec_pair( $k, $v ) ) {
						$norm_k = self::normalize_spec_key( $k, $category );
						if ( empty( $allowed ) || in_array( $norm_k, $allowed, true ) ) {
							if ( ! isset( $specs[ $norm_k ] ) ) {
								$specs[ $norm_k ] = $v;
							}
						}
					}
				}
			}
		}

		// 4. Clean text key-value lines: "Key : Value"
		if ( empty( $specs ) ) {
			$clean_text = strip_tags( $html_snippet );
			$lines = explode( "\n", $clean_text );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( strpos( $line, ':' ) !== false ) {
					$parts = explode( ':', $line, 2 );
					$k = trim( $parts[0] );
					$v = trim( $parts[1] );
					if ( self::is_valid_spec_pair( $k, $v ) ) {
						$norm_k = self::normalize_spec_key( $k, $category );
						if ( empty( $allowed ) || in_array( $norm_k, $allowed, true ) ) {
							if ( ! isset( $specs[ $norm_k ] ) ) {
								$specs[ $norm_k ] = $v;
							}
						}
					}
				}
			}
		}

		return $specs;
	}

	/**
	 * Merge vendor extracted specs with category schema rules and domain regex extraction.
	 * Strictly deduplicates keys and guarantees no duplicates on UI/postmeta.
	 *
	 * @param string $category
	 * @param array $raw_specs
	 * @param array $existing_specs
	 * @param string $text_context
	 * @return array Clean dictionary conforming strictly to the category schema without duplicate keys.
	 */
	public static function merge_and_clean_specs( $category, $raw_specs = array(), $existing_specs = array(), $text_context = '' ) {
		$merged = array();
		$cat = self::normalize_category_slug( $category );
		$allowed = isset( self::$allowed_specs_by_category[ $cat ] ) ? self::$allowed_specs_by_category[ $cat ] : null;

		// 1. Sanitize and normalize existing specs
		if ( is_array( $existing_specs ) ) {
			foreach ( $existing_specs as $k => $v ) {
				if ( $k === 'raw_specs_table' || ! is_scalar( $v ) ) {
					continue;
				}
				if ( self::is_valid_spec_pair( $k, $v ) ) {
					$norm_k = self::normalize_spec_key( $k, $category );
					if ( empty( $allowed ) || in_array( $norm_k, $allowed, true ) ) {
						$clean_v = self::sanitize_and_validate_spec_value( $norm_k, $v, $category, $text_context );
						if ( ! empty( $clean_v ) ) {
							$merged[ $norm_k ] = (string) $clean_v;
						}
					}
				}
			}
		}

		// 2. Add newly extracted vendor specs
		if ( is_array( $raw_specs ) ) {
			foreach ( $raw_specs as $k => $v ) {
				if ( self::is_valid_spec_pair( $k, $v ) ) {
					$norm_k = self::normalize_spec_key( $k, $category );
					if ( empty( $allowed ) || in_array( $norm_k, $allowed, true ) ) {
						if ( ! isset( $merged[ $norm_k ] ) || empty( $merged[ $norm_k ] ) ) {
							$clean_v = self::sanitize_and_validate_spec_value( $norm_k, $v, $category, $text_context );
							if ( ! empty( $clean_v ) ) {
								$merged[ $norm_k ] = (string) $clean_v;
							}
						}
					}
				}
			}
		}

		// 3. Regex Auto-Fill for Missing Schema Attributes based on text context
		$text = $text_context;

		switch ( $cat ) {
			case 'cpu':
				if ( empty( $merged['Socket'] ) ) {
					$inferred_socket = self::resolve_cpu_socket_from_title( $text );
					if ( ! empty( $inferred_socket ) ) {
						$merged['Socket'] = $inferred_socket;
					}
				}
				if ( empty( $merged['Number of Cores'] ) && preg_match( '/\b(\d+)\s*(?:-|\s*)?(?:core|cores)\b/i', $text, $m ) ) {
					$merged['Number of Cores'] = $m[1];
				}
				if ( empty( $merged['Number of Threads'] ) && preg_match( '/\b(\d+)\s*(?:-|\s*)?(?:thread|threads)\b/i', $text, $m ) ) {
					$merged['Number of Threads'] = $m[1];
				}
				if ( empty( $merged['Turbo Clock'] ) && preg_match( '/(?:up\s*to|boost|max\s*clock|turbo)?\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$merged['Turbo Clock'] = $m[1] . ' GHz';
				}
				if ( empty( $merged['Frequency'] ) && preg_match( '/(?:base|base\s*clock)\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$merged['Frequency'] = $m[1] . ' GHz';
				}
				if ( empty( $merged['Cache L3'] ) && preg_match( '/(\d+)\s*(?:MB|Mb)\s*(?:L3|Cache|3D\s*V-Cache|Smart\s*Cache)/i', $text, $m ) ) {
					$merged['Cache L3'] = $m[1] . ' MB';
				}
				if ( empty( $merged['TDP'] ) && preg_match( '/(\d+)\s*W(?:att)?\b/i', $text, $m ) ) {
					$merged['TDP'] = $m[1] . 'W';
				}
				if ( empty( $merged['Memory Support'] ) && preg_match( '/\b(DDR5(?:\s*\+\s*DDR4)?|DDR4|DDR5)\b/i', $text, $m ) ) {
					$merged['Memory Support'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Integrated Graphics'] ) && preg_match( '/\b(Intel\s*UHD\s*Graphics\s*\d+|Radeon\s*Graphics|Vega\s*\d+|Intel\s*Graphics)\b/i', $text, $m ) ) {
					$merged['Integrated Graphics'] = $m[1];
				}
				break;

			case 'gpu':
				if ( empty( $merged['Memory Size'] ) && preg_match( '/(\d+)\s*(?:GB|G)\s*(GDDR6X|GDDR6|GDDR5X|GDDR5|HBM2e|HBM3)/i', $text, $m ) ) {
					$merged['Memory Size'] = $m[1] . ' GB';
					if ( empty( $merged['Memory Type'] ) ) {
						$merged['Memory Type'] = strtoupper( $m[2] );
					}
				} elseif ( empty( $merged['Memory Size'] ) && preg_match( '/(\d+)\s*(?:GB|G)\b/i', $text, $m ) ) {
					$merged['Memory Size'] = $m[1] . ' GB';
				}
				if ( empty( $merged['GPU Name'] ) && preg_match( '/\b(RTX\s*4090|RTX\s*4080\s*Super|RTX\s*4080|RTX\s*4070\s*Ti\s*Super|RTX\s*4070\s*Ti|RTX\s*4070\s*Super|RTX\s*4070|RTX\s*4060\s*Ti|RTX\s*4060|RTX\s*3060|RX\s*7900\s*XTX|RX\s*7900\s*XT|RX\s*7800\s*XT|RX\s*7700\s*XT|RX\s*7600\s*XT|RX\s*7600|Arc\s*A770|Arc\s*A750)\b/i', $text, $m ) ) {
					$merged['GPU Name'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Memory Bus'] ) && preg_match( '/(\d+)\s*(?:-|\s*)?bit\b/i', $text, $m ) ) {
					$merged['Memory Bus'] = $m[1] . '-bit';
				}
				if ( empty( $merged['Suggested PSU'] ) && preg_match( '/(?:PSU|Power Supply|Recommended PSU|Min PSU)[^\d]*(\d{3,4})\s*W/i', $text, $m ) ) {
					$merged['Suggested PSU'] = $m[1] . ' W';
				}
				break;

			case 'motherboard':
				$chip_match = null;
				if ( preg_match( '/\b(X870E|X870|X670E|X670|B850|B840|B650E|B650|A620|X570S|X570|B550|A520|X470|B450|X370|B350|A320|WRX90|TRX50|WRX80|TRX40|X399|Z890|B860|H810|Z790|B760|H770|Z690|B660|H670|H610|Z590|B560|H570|H510|Z490|B460|H470|H410|Z390|Z370|B365|B360|H370|H310|Z270|H270|B250|Z170|H170|B150|H110|X299|X99|Z97|H97|Z87|H87|B85|H81)(?:\b|[MIAE\-])/i', $text, $cm ) ) {
					$chip_match = strtoupper( $cm[1] );
					if ( empty( $merged['Chipset'] ) ) {
						$merged['Chipset'] = $chip_match;
					}
				}

				if ( $chip_match && isset( self::$motherboard_chipsets[ $chip_match ] ) ) {
					$info = self::$motherboard_chipsets[ $chip_match ];
					if ( empty( $merged['Platform'] ) || ( $merged['Platform'] !== $info['platform'] ) ) {
						$merged['Platform'] = $info['platform'];
					}
					if ( empty( $merged['Socket'] ) ) {
						$merged['Socket'] = $info['socket'];
					}
					if ( empty( $merged['Cpu Type'] ) ) {
						$merged['Cpu Type'] = ( $info['platform'] === 'Intel' ) ? 'Intel Core' : 'AMD Ryzen';
					}
					if ( ! empty( $info['mem'] ) && empty( $merged['Supported Memory Type'] ) ) {
						$merged['Supported Memory Type'] = $info['mem'];
					}
				}

				if ( empty( $merged['Socket'] ) ) {
					$inferred_socket = self::resolve_cpu_socket_from_title( $text );
					if ( ! empty( $inferred_socket ) ) {
						$merged['Socket'] = $inferred_socket;
					}
				}
				if ( empty( $merged['Form Factor'] ) && preg_match( '/\b(E-ATX|Extended ATX|ATX|Micro-ATX|Micro ATX|mATX|Mini-ITX|Mini ITX|ITX)\b/i', $text, $m ) ) {
					$merged['Form Factor'] = strtoupper( str_replace( ' ', '-', $m[1] ) );
				}
				if ( empty( $merged['Supported Memory Type'] ) && preg_match( '/\b(DDR5|DDR4)\b/i', $text, $m ) ) {
					$merged['Supported Memory Type'] = strtoupper( $m[1] );
				}
				break;

			case 'ram':
				if ( empty( $merged['Memory Type'] ) && preg_match( '/\b(DDR5|DDR4|DDR3)\b/i', $text, $m ) ) {
					$merged['Memory Type'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Capacity'] ) && preg_match( '/\b(\d+GB(?:\s*\(\d+x\d+GB\))?|\d+\s*GB)\b/i', $text, $m ) ) {
					$merged['Capacity'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Speed'] ) && preg_match( '/(\d{4,5})\s*(?:MHz|MT\/s)/i', $text, $m ) ) {
					$merged['Speed'] = $m[1] . ' MHz';
				}
				if ( empty( $merged['Tested Latency'] ) && preg_match( '/\b(CL\s*\d{2}(?:-\d{2}-\d{2}-\d{2})?)\b/i', $text, $m ) ) {
					$merged['Tested Latency'] = strtoupper( $m[1] );
				}
				break;

			case 'psu':
				if ( empty( $merged['Wattage'] ) ) {
					$inferred_wattage = self::resolve_psu_wattage_from_title( $text );
					if ( ! empty( $inferred_wattage ) ) {
						$merged['Wattage'] = $inferred_wattage;
					}
				}
				if ( empty( $merged['Certification'] ) && preg_match( '/(80\s*Plus\s*(?:Titanium|Platinum|Gold|Silver|Bronze|White|Standard)|80\+?\s*Gold)/i', $text, $m ) ) {
					$merged['Certification'] = ucwords( $m[1] );
				}
				if ( empty( $merged['Modular'] ) && preg_match( '/(Fully\s*Modular|Full\s*Modular|Semi-Modular|Semi\s*Modular|Non-Modular)/i', $text, $m ) ) {
					$merged['Modular'] = ucwords( $m[1] );
				}
				break;

			case 'cooler':
				if ( empty( $merged['Radiator Size'] ) && preg_match( '/\b(360|280|240|120|420)\s*(?:mm)?\b/i', $text, $m ) ) {
					$merged['Radiator Size'] = $m[1] . ' mm';
					if ( empty( $merged['Cooling Type'] ) ) {
						$merged['Cooling Type'] = 'Liquid / AIO Cooler';
					}
				}
				if ( empty( $merged['Cooling Type'] ) && preg_match( '/(AIO|Liquid\s*Cooler|Water\s*Cooler)/i', $text ) ) {
					$merged['Cooling Type'] = 'Liquid / AIO Cooler';
				} elseif ( empty( $merged['Cooling Type'] ) && preg_match( '/(Air\s*Cooler|Tower\s*Cooler)/i', $text ) ) {
					$merged['Cooling Type'] = 'Air Cooler';
				}
				break;

			case 'storage':
				if ( empty( $merged['Capacity'] ) && preg_match( '/\b(\d+\s*(?:TB|GB))\b/i', $text, $m ) ) {
					$merged['Capacity'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Form Factor'] ) && preg_match( '/(M\.2\s*2280|M\.2|2\.5\s*inch|2\.5")/i', $text, $m ) ) {
					$merged['Form Factor'] = 'M.2 2280';
				}
				if ( empty( $merged['Interface'] ) && preg_match( '/(PCIe\s*5\.0(?:\s*x4)?|PCIe\s*4\.0(?:\s*x4)?|Gen4|Gen5|SATA\s*III|SATA\s*6Gb\/s)/i', $text, $m ) ) {
					$merged['Interface'] = strtoupper( $m[1] );
				}
				break;

			case 'case_fan':
				if ( empty( $merged['Fan Size'] ) && preg_match( '/\b(120|140|80|92|200)\s*(?:mm)?\b/i', $text, $m ) ) {
					$merged['Fan Size'] = $m[1] . ' mm';
				}
				if ( empty( $merged['Lighting'] ) && preg_match( '/(ARGB|Addressable\s*RGB|RGB|Auto\s*RGB|LED)/i', $text, $m ) ) {
					$merged['Lighting'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Package Quantity'] ) && preg_match( '/\b(Triple\s*Pack|3-Pack|3-in-1|3\s*Fan\s*Pack|Twin\s*Pack|2-Pack|Single\s*Pack|Single\s*Fan)\b/i', $text, $m ) ) {
					$merged['Package Quantity'] = ucwords( $m[1] );
				}
				if ( empty( $merged['PWM Support'] ) && preg_match( '/\b(PWM)\b/i', $text ) ) {
					$merged['PWM Support'] = 'Yes';
				}
				break;

			case 'cabinet':
				if ( empty( $merged['Cabinet Size'] ) && preg_match( '/(Mid\s*Tower|Full\s*Tower|Mini\s*Tower|Micro\s*ATX|Mini\s*ITX|SFF)/i', $text, $m ) ) {
					$merged['Cabinet Size'] = ucwords( $m[1] );
				}
				if ( empty( $merged['Motherboard Size'] ) && preg_match( '/(E-ATX|ATX|Micro-ATX|Mini-ITX)/i', $text, $m ) ) {
					$merged['Motherboard Size'] = strtoupper( $m[1] );
				}
				break;
		}

		// Final strict filter: if allowed list is defined, discard any non-schema key and preserve schema order
		if ( ! empty( $allowed ) ) {
			$filtered = array();
			foreach ( $allowed as $allowed_key ) {
				if ( isset( $merged[ $allowed_key ] ) && $merged[ $allowed_key ] !== '' ) {
					$filtered[ $allowed_key ] = $merged[ $allowed_key ];
				}
			}
			return $filtered;
		}

		return $merged;
	}

	/**
	 * Backwards-compatible domain regex extraction helper for structured specs.
	 *
	 * @param string $category
	 * @param string $text
	 * @param array $existing_specs
	 * @return array
	 */
	public static function extract_detailed_specs( $category, $text, $existing_specs = array() ) {
		$specs = is_array( $existing_specs ) ? $existing_specs : array();
		$cat = strtolower( $category );

		switch ( $cat ) {
			case 'cpu':
				if ( preg_match( '/\b(AM5|AM4|LGA1700|LGA1851|LGA1200|LGA1151|sTR5|SP5)\b/i', $text, $m ) ) {
					$specs['socket'] = strtoupper( $m[1] );
				}
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?(?:core|cores)\b/i', $text, $m ) ) {
					$specs['cores'] = intval( $m[1] );
				}
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?(?:thread|threads)\b/i', $text, $m ) ) {
					$specs['threads'] = intval( $m[1] );
				}
				if ( preg_match( '/(?:up\s*to|boost|max\s*clock|turbo)?\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$specs['boost_clock'] = $m[1] . ' GHz';
				}
				if ( preg_match( '/(?:base|base\s*clock)\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$specs['base_clock'] = $m[1] . ' GHz';
				}
				if ( preg_match( '/(\d+)\s*(?:MB|Mb)\s*(?:L3|Cache|3D\s*V-Cache|Smart\s*Cache)/i', $text, $m ) ) {
					$specs['cache'] = $m[1] . 'MB';
				}
				if ( preg_match( '/(\d+)\s*W(?:att)?\b/i', $text, $m ) ) {
					$specs['tdp'] = $m[1] . 'W';
				}
				break;

			case 'gpu':
				if ( preg_match( '/(\d+)\s*(?:GB|G)\s*(GDDR6X|GDDR6|GDDR5X|GDDR5|HBM2e|HBM3)/i', $text, $m ) ) {
					$specs['vram_size'] = $m[1] . 'GB';
					$specs['memory_type'] = strtoupper( $m[2] );
				} elseif ( preg_match( '/(\d+)\s*(?:GB|G)\b/i', $text, $m ) ) {
					$specs['vram_size'] = $m[1] . 'GB';
				}
				if ( preg_match( '/\b(RTX\s*4090|RTX\s*4080\s*Super|RTX\s*4080|RTX\s*4070\s*Ti\s*Super|RTX\s*4070\s*Ti|RTX\s*4070\s*Super|RTX\s*4070|RTX\s*4060\s*Ti|RTX\s*4060|RTX\s*3060|RX\s*7900\s*XTX|RX\s*7900\s*XT|RX\s*7800\s*XT|RX\s*7700\s*XT|RX\s*7600\s*XT|RX\s*7600|Arc\s*A770|Arc\s*A750)\b/i', $text, $m ) ) {
					$specs['gpu_chipset'] = strtoupper( $m[1] );
				}
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?bit\b/i', $text, $m ) ) {
					$specs['memory_bus'] = $m[1] . '-bit';
				}
				if ( preg_match( '/(?:PSU|Power Supply|Recommended PSU|Min PSU)[^\d]*(\d{3,4})\s*W/i', $text, $m ) ) {
					$specs['recommended_psu'] = $m[1] . 'W';
				}
				break;
		}

		return $specs;
	}

	protected static function format_specs_summary( $specs ) {
		if ( empty( $specs ) || ! is_array( $specs ) ) {
			return 'None';
		}
		$parts = array();
		foreach ( $specs as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$parts[] = "{$k}: {$v}";
			}
		}
		return ! empty( $parts ) ? implode( ' | ', array_slice( $parts, 0, 5 ) ) : 'Specs recorded';
	}

	protected function make_http_request( $url, $headers = array() ) {
		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init();
			$default_headers = array(
				'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.9',
			);

			foreach ( $headers as $k => $v ) {
				$default_headers[] = "{$k}: {$v}";
			}

			curl_setopt_array( $ch, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_TIMEOUT        => 20,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_ENCODING       => '',
				CURLOPT_HTTPHEADER     => $default_headers,
			) );

			$body = curl_exec( $ch );
			$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			return array(
				'success' => ( $code >= 200 && $code < 400 ),
				'code'    => $code,
				'body'    => $body,
			);
		}

		$response = \wp_remote_get( $url, array(
			'timeout' => 20,
			'headers' => array_merge( array(
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
			), $headers ),
		) );

		if ( \is_wp_error( $response ) ) {
			return array( 'success' => false, 'code' => 500, 'body' => '' );
		}

		$code = \wp_remote_retrieve_response_code( $response );
		$body = \wp_remote_retrieve_body( $response );

		return array(
			'success' => ( $code >= 200 && $code < 400 ),
			'code'    => $code,
			'body'    => $body,
		);
	}

	protected function emit( $logger, $level, $message ) {
		if ( is_callable( $logger ) ) {
			call_user_func( $logger, $level, $message, array(
				'timestamp' => current_time( 'H:i:s' ),
			) );
		}
	}
}
