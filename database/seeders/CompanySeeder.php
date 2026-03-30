<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name' => 'NovaTech Solutions',
                'code' => 'NOVATECH',
                'email' => 'hello@novatech.example',
                'phone' => '+1-202-555-0101',
                'address' => '1450 Harbor Center Drive, Seattle, WA 98101',
                'is_active' => true,
            ],
            [
                'name' => 'BlueRiver Logistics',
                'code' => 'BLUERIV',
                'email' => 'operations@blueriver.example',
                'phone' => '+1-202-555-0102',
                'address' => '88 Westport Avenue, Portland, OR 97204',
                'is_active' => true,
            ],
            [
                'name' => 'Orion Systems Ltd.',
                'code' => 'ORIONSYS',
                'email' => 'contact@orionsystems.example',
                'phone' => '+1-202-555-0103',
                'address' => '420 Meridian Plaza, Denver, CO 80202',
                'is_active' => true,
            ],
            [
                'name' => 'EverPeak Manufacturing',
                'code' => 'EVERPEAK',
                'email' => 'sales@everpeak.example',
                'phone' => '+1-202-555-0104',
                'address' => '19 Foundry Road, Cleveland, OH 44114',
                'is_active' => true,
            ],
            [
                'name' => 'CedarStone Health',
                'code' => 'CEDARHLT',
                'email' => 'info@cedarstone.example',
                'phone' => '+1-202-555-0105',
                'address' => '250 Lakeside Campus, Madison, WI 53703',
                'is_active' => true,
            ],
            [
                'name' => 'NorthGate Retail Group',
                'code' => 'NGRETAIL',
                'email' => 'support@northgate.example',
                'phone' => '+1-202-555-0106',
                'address' => '612 Market Square, Columbus, OH 43215',
                'is_active' => true,
            ],
            [
                'name' => 'Atlas Energy Partners',
                'code' => 'ATLASEN',
                'email' => 'partners@atlasenergy.example',
                'phone' => '+1-202-555-0107',
                'address' => '901 Canyon Tower, Houston, TX 77002',
                'is_active' => true,
            ],
            [
                'name' => 'SummitLine Finance',
                'code' => 'SUMMITFN',
                'email' => 'desk@summitline.example',
                'phone' => '+1-202-555-0108',
                'address' => '77 Capital Circle, Charlotte, NC 28202',
                'is_active' => true,
            ],
            [
                'name' => 'GreenField Foods',
                'code' => 'GREENFLD',
                'email' => 'team@greenfieldfoods.example',
                'phone' => '+1-202-555-0109',
                'address' => '134 Orchard Park, Sacramento, CA 95814',
                'is_active' => true,
            ],
            [
                'name' => 'LumenWorks Media',
                'code' => 'LUMENWK',
                'email' => 'studio@lumenworks.example',
                'phone' => '+1-202-555-0110',
                'address' => '500 Story Lane, Austin, TX 78701',
                'is_active' => true,
            ],
            [
                'name' => 'HarborView Properties',
                'code' => 'HARBORVW',
                'email' => 'leasing@harborview.example',
                'phone' => '+1-202-555-0111',
                'address' => '300 Bayfront Center, San Diego, CA 92101',
                'is_active' => true,
            ],
            [
                'name' => 'SkyBridge Analytics',
                'code' => 'SKYBRDG',
                'email' => 'analytics@skybridge.example',
                'phone' => '+1-202-555-0112',
                'address' => '48 Insight Avenue, Boston, MA 02110',
                'is_active' => true,
            ],
            [
                'name' => 'WestForge Industrial',
                'code' => 'WESTFRG',
                'email' => 'contact@westforge.example',
                'phone' => '+1-202-555-0113',
                'address' => '1700 Mill District Road, Pittsburgh, PA 15222',
                'is_active' => true,
            ],
            [
                'name' => 'BrightPath Education',
                'code' => 'BRGTPATH',
                'email' => 'admin@brightpath.example',
                'phone' => '+1-202-555-0114',
                'address' => '215 Learning Commons, Raleigh, NC 27601',
                'is_active' => true,
            ],
            [
                'name' => 'IronOak Security',
                'code' => 'IRONOAK',
                'email' => 'security@ironoak.example',
                'phone' => '+1-202-555-0115',
                'address' => '91 Sentinel Way, Arlington, VA 22202',
                'is_active' => true,
            ],
            [
                'name' => 'SilverWave Telecom',
                'code' => 'SILVWAVE',
                'email' => 'network@silverwave.example',
                'phone' => '+1-202-555-0116',
                'address' => '830 Exchange Boulevard, Phoenix, AZ 85004',
                'is_active' => true,
            ],
            [
                'name' => 'Oakridge Civil Works',
                'code' => 'OAKRIDGE',
                'email' => 'projects@oakridge.example',
                'phone' => '+1-202-555-0117',
                'address' => '140 Mason Street, Kansas City, MO 64106',
                'is_active' => true,
            ],
            [
                'name' => 'PrimeHarbor Ventures',
                'code' => 'PRMHARB',
                'email' => 'ventures@primeharbor.example',
                'phone' => '+1-202-555-0118',
                'address' => '655 Venture Dock, Miami, FL 33131',
                'is_active' => true,
            ],
            [
                'name' => 'UrbanLeaf Design Co.',
                'code' => 'URBANLEF',
                'email' => 'design@urbanleaf.example',
                'phone' => '+1-202-555-0119',
                'address' => '72 Creative District, Minneapolis, MN 55402',
                'is_active' => true,
            ],
            [
                'name' => 'Cobalt Freight Lines',
                'code' => 'COBALTFL',
                'email' => 'dispatch@cobaltfreight.example',
                'phone' => '+1-202-555-0120',
                'address' => '410 Transit Park, Memphis, TN 38103',
                'is_active' => false,
            ],
            [
                'name' => 'RedPine Hospitality',
                'code' => 'REDPINE',
                'email' => 'guestservices@redpine.example',
                'phone' => '+1-202-555-0121',
                'address' => '39 Summit Lodge Road, Salt Lake City, UT 84111',
                'is_active' => false,
            ],
            [
                'name' => 'MetroCore Services',
                'code' => 'METROCOR',
                'email' => 'service@metrocore.example',
                'phone' => '+1-202-555-0122',
                'address' => '980 Central Avenue, Chicago, IL 60603',
                'is_active' => false,
            ],
            [
                'name' => 'AsterPoint Biotech',
                'code' => 'ASTERBIO',
                'email' => 'lab@asterpoint.example',
                'phone' => '+1-202-555-0123',
                'address' => '60 Research Parkway, San Jose, CA 95113',
                'is_active' => false,
            ],
            [
                'name' => 'BrookStone Legal Group',
                'code' => 'BROOKLG',
                'email' => 'counsel@brookstonelegal.example',
                'phone' => '+1-202-555-0124',
                'address' => '250 Justice Plaza, Philadelphia, PA 19103',
                'is_active' => false,
            ],
            [
                'name' => 'PolarAxis Aviation',
                'code' => 'POLARAX',
                'email' => 'flightops@polaraxis.example',
                'phone' => '+1-202-555-0125',
                'address' => '12 Runway Center, Nashville, TN 37203',
                'is_active' => true,
            ],
        ];

        foreach ($companies as $company) {
            Company::query()->updateOrCreate(
                ['code' => $company['code']],
                $company,
            );
        }
    }
}
