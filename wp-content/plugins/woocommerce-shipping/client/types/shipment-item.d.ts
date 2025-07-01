import { OrderItem } from './order-item';

export type ShipmentSubItem = ShipmentItem & {
	id: string;
	parentId: ShipmentItem.id;
};

export interface ShipmentItem extends OrderItem {
	subItems: SubItem[];
}

