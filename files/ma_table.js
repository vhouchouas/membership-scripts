'use strict';

const e = React.createElement;

class MATh extends React.Component {
  constructor(props) {
    super(props);
    this.onClick = this.onClick.bind(this);
  }

  onClick() {
    this.props.onOrderByChange(this.props.name);
  }

  render(){
    return <th style={{cursor: "pointer"}} onClick={this.onClick}>{this.props.name.toString()}</th>;
  }
}

class MATd extends React.Component {
  constructor(props) {
    super(props);
  }

  render(){
    return <td>{this.props.data}</td>;
  }
}

class MARow extends React.Component {
  constructor(props) {
    super(props);
  }

  render(){
    const cells = [];
    for (const key in this.props.item){
      cells.push(<MATd key={key} data={this.props.item[key]} />);
    }
    return <tr>{cells}</tr>;
  }
}

class MATable extends React.Component {
  constructor(props) {
    super(props);
    this.state = {order_by: this.props.initialOrderBy};
    this.onOrderByChange = this.onOrderByChange.bind(this);
  }

  onOrderByChange(newOrderBy){
    this.setState({order_by: newOrderBy});
  }

  sort(items, order_by) {
    const copy = items.slice();
    copy.sort(function (item1, item2){
      if (item1[order_by] < item2[order_by]){
        return -1;
      }
      if (item1[order_by] > item2[order_by]){
        return 1;
      }
      return 0;
    });
    return copy;
  }

  render() {
    // TODO: handle the case where there is no data
    const orderedItems = this.sort(this.props.items, this.state.order_by);

    const colHeaders = [];
    for (const key in orderedItems[0]){
      colHeaders.push(<MATh key={key} name={key} onOrderByChange={this.onOrderByChange} />)
    }

    const rows = [];
    orderedItems.forEach(item => rows.push(<MARow key={item.Mail} item={item} />))

    return (
        <table>
          <thead><tr>{colHeaders}</tr></thead>
          <tbody>{rows}</tbody>
        </table>
        );
  }
}

class MAComponent extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      items: JSON.parse(window.ma_items).map(this.toFrontItem)
    };
  }

  toFrontItem(item) {
    return {
      "Derniere date d'adhesion": item.event_date,
        "Nom": item.first_name + " " + item.last_name,
        "Mail": item.email,
        "Code Postal": item.postal_code
    };
  }

  render() {
    return (
        <div>
        <form action={window.self_page} method="get">
          Remonter jusqu'a <input name="since" type="date" />
        </form>
        <MATable items={this.state.items} initialOrderBy="Derniere date d'adhesion" />
        </div>
        );
  }
}

const domContainer = document.querySelector('#ma_table');
ReactDOM.render(e(MAComponent), domContainer);
