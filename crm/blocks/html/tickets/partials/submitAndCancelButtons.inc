<?php
/**
 * @copyright 2012-2013 City of Bloomington, Indiana
 * @license http://www.gnu.org/licenses/agpl.txt GNU/AGPL, see LICENSE.txt
 * @author Cliff Ingham <inghamn@bloomington.in.gov>
 * @param Ticket $this->ticket
 */
$onclick = isset($_REQUEST['callback'])
	? 'onclick="self.close();"'
	: '';
echo "
<button type=\"submit\"><span class=\"icon-ok\"></span> Submit</button>
<a class=\"btn\" href=\"{$this->ticket->getURL()}\" $onclick><span class=\"icon-remove\"></span> Cancel</a>
";
