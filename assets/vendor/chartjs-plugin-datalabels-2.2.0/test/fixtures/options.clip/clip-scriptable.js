var data = [
  {x: 0, y: 0},
  {x: 0, y: 1},
  {x: 0, y: 2},
  {x: 1, y: 0},
  {x: 1, y: 1},
  {x: 1, y: 2},
  {x: 2, y: 0},
  {x: 2, y: 1},
  {x: 2, y: 2}
];

export default {
  config: {
    type: 'bubble',
    data: {
      datasets: [{
        data: data,
        datalabels: {
          backgroundColor: '#0f7',
          borderColor: 'black',
          padding: 16
        }
      }, {
        data: data,
        datalabels: {
          backgroundColor: '#f07',
          borderColor: 'white',
          padding: 32
        }
      }]
    },
    options: {
      layout: {
        padding: 48
      },
      plugins: {
        datalabels: {
          borderWidth: 4,
          clip: function(ctx) {
            return (ctx.dataIndex + ctx.datasetIndex) % 2 === 1;
          },
          font: {
            size: 0
          }
        }
      }
    }
  },
  options: {
    canvas: {
      height: 256,
      width: 256
    }
  }
};
