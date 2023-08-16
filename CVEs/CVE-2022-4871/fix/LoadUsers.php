<?php

class JSON_LoadUsers extends JSONAdmin
{
	public function execute()
	{
		$sort		= Functions::Post( 'sort' );
		$direction	= Functions::Post( 'direction' );

		if ( !$this->_Load_Users( $sort, $direction, $users ) )
		{
			return false;
		}

		foreach( $users as &$loaded_user )
		{
			$loaded_user[ 'last_on' ] 			= Functions::FormatDate( $loaded_user[ 'last_on' ] );
			$loaded_user[ 'current_place' ] 	= Functions::Place( $loaded_user[ 'current_place' ] );
		}

		return $this->setData( $users );
	}

	// Helper functions

	private function _Load_Users( $sort, $direction, &$users )
	{
		$db_weeks	= new Weeks( $this->_db );
		$current	= $db_weeks->Current();
		$direction 	= ( $direction === 'asc' ) ? 'ASC' : 'DESC';

		switch ( $sort )
		{
			case 'name'				:
			case 'current_place'	:
			case 'last_on'			:
			case 'paid'				:
			case 'failed_logins'	:
			case 'active_sessions'	:
			case 'remaining'		:
			{
				break;
			}
			default					:
			{
				return $this->setError( array( '#Error#', 'Invalid sort' ) );
			}
		}

		$sql 		= "SELECT
							u.*,
							CONCAT( u.fname, ' ', u.lname ) AS name,
							( SELECT COUNT( * ) FROM failed_logins WHERE email = u.email ) AS failed_logins,
							( SELECT COUNT( * ) FROM sessions WHERE userid = u.id ) AS active_sessions,
							COUNT( g.id ) AS remaining
						FROM
							users u
							LEFT OUTER JOIN games g ON 1 = 1
							LEFT OUTER JOIN picks p ON p.game_id = g.id AND p.user_id = u.id
						WHERE
							g.week = ? AND
							p.id IS NULL
						GROUP BY
							u.id
						ORDER BY
							{$sort} {$direction}";

		if ( !$this->_db->select( $sql, $users, $current ) )
		{
			return $this->setDBError();
		}

		return true;
	}
}
