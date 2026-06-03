import handover from './handover'
import bookings from './bookings'
import requisitions from './requisitions'
import reports from './reports'
import products from './products'
import receiving from './receiving'
import movements from './movements'
const inventory = {
    handover: Object.assign(handover, handover),
bookings: Object.assign(bookings, bookings),
requisitions: Object.assign(requisitions, requisitions),
reports: Object.assign(reports, reports),
products: Object.assign(products, products),
receiving: Object.assign(receiving, receiving),
movements: Object.assign(movements, movements),
}

export default inventory