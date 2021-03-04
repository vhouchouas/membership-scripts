import React, { Component} from "react";
import SortableTable from 'react-sortable-table';
//import "./App.css";

class MATable extends Component{
  constructor(props) {
    super(props);
    this.state = {
      data: JSON.parse(window.ma_items).map(this.toFrontItem)
    }
  }

  toFrontItem(item){
    return {
      'event_date': item.event_date,
      'name': item.first_name + " " + item.last_name,
      'mail': item.email,
      'postal_code': item.postal_code
    }
  }

  render(){
    const columns = [
      {
        header: 'Dernière date d\'adhésion',
        key: 'event_date',
        defaultSorting: 'ASC'
      },
      {
        header: 'Nom',
        key: 'name'
      },
      {
        header: 'Mail',
        key: 'email'
      },
      {
        header: 'Code Postal',
        key: 'postal_code'
      }
    ];

    return(
      <SortableTable
        data={this.state.data}
        columns={columns} />
    );
  }
}

export default MATable;
